<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 — Shared master data (docs/04 §5, ADR 0016): wilayah, orang, pegawai, tahun anggaran,
 * serta pendaftaran consumer entity bersama. Setiap baris master juga punya baris `objects`
 * (ADR 0005) sehingga bisa menjadi target relasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE core_regions (
                id         uuid PRIMARY KEY REFERENCES objects(id),
                code       text NOT NULL UNIQUE CHECK (code ~ '^[0-9]{2}(\.[0-9]{2}(\.[0-9]{2}(\.[0-9]{4})?)?)?$'),
                name       text NOT NULL CHECK (length(name) BETWEEN 1 AND 150),
                level      smallint NOT NULL CHECK (level BETWEEN 1 AND 4),
                parent_id  uuid REFERENCES core_regions(id),
                source_ref text NOT NULL,
                valid_to   date,
                CHECK ((level = 1) = (parent_id IS NULL))
            );
            CREATE INDEX core_regions_parent_idx ON core_regions (parent_id);
            -- Ekspresi sama persis dengan label di core_object_labels agar pencarian relasi memakai index.
            CREATE INDEX core_regions_label_trgm ON core_regions USING gin ((name || ' (' || code || ')') gin_trgm_ops);

            CREATE TABLE core_persons (
                id         uuid PRIMARY KEY REFERENCES objects(id),
                full_name  text NOT NULL CHECK (length(full_name) BETWEEN 1 AND 150),
                -- HMAC-SHA256(NIK, pepper) hex: untuk cari persis & cegah ganda, bukan tampilan.
                nik_hash   text UNIQUE CHECK (nik_hash ~ '^[0-9a-f]{64}$'),
                -- NIK terenkripsi aplikasi (AES-256, kunci BARA_PII_KEY). Tidak pernah plaintext.
                nik_enc    text,
                nik_last4  text CHECK (nik_last4 ~ '^[0-9]{4}$'),
                birth_date date,
                email      citext,
                phone      text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CHECK ((nik_hash IS NULL) = (nik_enc IS NULL))
            );
            CREATE INDEX core_persons_label_trgm ON core_persons USING gin ((full_name || COALESCE(' · NIK ****' || nik_last4, '')) gin_trgm_ops);

            CREATE TABLE core_employees (
                id         uuid PRIMARY KEY REFERENCES objects(id),
                person_id  uuid NOT NULL REFERENCES core_persons(id),
                nip        text UNIQUE CHECK (nip ~ '^[0-9]{18}$'),
                org_id     uuid NOT NULL REFERENCES core_organizations(id),
                position   text CHECK (length(position) <= 150),
                rank       text CHECK (length(rank) <= 60),
                valid_from date NOT NULL,
                valid_to   date,
                created_at timestamptz NOT NULL DEFAULT now(),
                CHECK (valid_to IS NULL OR valid_to >= valid_from)
            );
            CREATE INDEX core_employees_person_idx ON core_employees (person_id);
            CREATE INDEX core_employees_org_idx ON core_employees (org_id);

            CREATE TABLE core_fiscal_years (
                id        uuid PRIMARY KEY REFERENCES objects(id),
                year      smallint NOT NULL UNIQUE CHECK (year BETWEEN 2000 AND 2100),
                starts_on date NOT NULL,
                ends_on   date NOT NULL,
                status    text NOT NULL CHECK (status IN ('planning','running','closed')),
                CHECK (ends_on > starts_on)
            );
            -- Hanya satu tahun anggaran berjalan.
            CREATE UNIQUE INDEX core_fiscal_years_one_running ON core_fiscal_years ((true)) WHERE status = 'running';

            ALTER TABLE core_organizations ADD COLUMN region_id uuid REFERENCES core_regions(id);
            ALTER TABLE users ADD COLUMN person_id uuid REFERENCES core_persons(id);

            CREATE TABLE entity_consumers (
                entity_id      uuid NOT NULL REFERENCES entities(id),
                application_id uuid NOT NULL REFERENCES applications(id),
                access         text NOT NULL DEFAULT 'reference' CHECK (access IN ('reference','read','aggregate')),
                status         text NOT NULL CHECK (status IN ('pending','approved','rejected','revoked')),
                reason         text NOT NULL CHECK (length(reason) BETWEEN 10 AND 1000),
                requested_by   uuid REFERENCES users(id),     -- NULL = dibuat migrasi
                requested_at   timestamptz NOT NULL DEFAULT now(),
                decided_by     uuid REFERENCES users(id),
                decided_at     timestamptz,
                decision_note  text CHECK (length(decision_note) <= 1000),
                PRIMARY KEY (entity_id, application_id),
                CHECK ((status = 'pending') = (decided_at IS NULL))
            );
            CREATE INDEX entity_consumers_pending_idx ON entity_consumers (requested_at) WHERE status = 'pending';

            -- Label semua objek Core fisik untuk pemilih & judul relasi (satu pintu, tanpa SQL dinamis).
            CREATE VIEW core_object_labels AS
                SELECT c.id, c.name AS label, c.path::text AS sort_key,
                       (c.valid_to IS NULL OR c.valid_to > CURRENT_DATE) AS is_active
                FROM core_organizations c
                UNION ALL
                SELECT r.id, r.name || ' (' || r.code || ')', r.code,
                       (r.valid_to IS NULL OR r.valid_to > CURRENT_DATE)
                FROM core_regions r
                UNION ALL
                SELECT p.id, p.full_name || COALESCE(' · NIK ****' || p.nik_last4, ''), lower(p.full_name), true
                FROM core_persons p
                UNION ALL
                SELECT e.id, p.full_name || COALESCE(' — ' || e.position, '') || ' (' || o.name || ')', lower(p.full_name),
                       (e.valid_to IS NULL OR e.valid_to > CURRENT_DATE)
                FROM core_employees e
                JOIN core_persons p ON p.id = e.person_id
                JOIN core_organizations o ON o.id = e.org_id
                UNION ALL
                SELECT f.id, 'Tahun Anggaran ' || f.year, lpad((9999 - f.year)::text, 4, '0'), f.status <> 'closed'
                FROM core_fiscal_years f;

            -- Relasi lintas aplikasi yang sudah terbit sebelum M4 tetap berlaku (grandfathering).
            INSERT INTO entity_consumers (entity_id, application_id, access, status, reason, decided_at, decision_note)
            SELECT DISTINCT r.target_entity_id, src.application_id, 'reference', 'approved',
                   'Relasi sudah terbit sebelum pendaftaran consumer (M4).', now(), 'Disetujui otomatis oleh migrasi.'
            FROM relationships r
            JOIN entities src ON src.id = r.source_entity_id
            JOIN entities tgt ON tgt.id = r.target_entity_id
            WHERE r.is_active AND src.application_id <> tgt.application_id
            ON CONFLICT DO NOTHING;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS core_object_labels;
            DROP TABLE IF EXISTS entity_consumers;
            ALTER TABLE users DROP COLUMN IF EXISTS person_id;
            ALTER TABLE core_organizations DROP COLUMN IF EXISTS region_id;
            DROP TABLE IF EXISTS core_fiscal_years;
            DROP TABLE IF EXISTS core_employees;
            DROP TABLE IF EXISTS core_persons;
            DROP TABLE IF EXISTS core_regions;
        SQL);
    }
};
