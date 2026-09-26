<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data runtime (docs/04 §4, ADR 0004, ADR 0014): records (JSONB berkunci field_key),
 * record_links (relasi), files (lampiran), serta fungsi cast IMMUTABLE untuk index ekspresi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE records (
                id                uuid PRIMARY KEY REFERENCES objects(id),
                entity_id         uuid NOT NULL REFERENCES entities(id),
                entity_version_id uuid NOT NULL REFERENCES entity_versions(id),
                data              jsonb NOT NULL DEFAULT '{}' CHECK (jsonb_typeof(data) = 'object'),
                title             text NOT NULL,
                search            tsvector GENERATED ALWAYS AS (to_tsvector('simple', title)) STORED,
                lock_version      integer NOT NULL DEFAULT 0,
                revision_of       uuid REFERENCES records(id),
                created_by        uuid NOT NULL REFERENCES users(id),
                updated_by        uuid NOT NULL REFERENCES users(id),
                created_at        timestamptz NOT NULL DEFAULT now(),
                updated_at        timestamptz NOT NULL DEFAULT now(),
                deleted_at        timestamptz
            );
            CREATE INDEX records_entity_created ON records (entity_id, created_at DESC, id DESC) WHERE deleted_at IS NULL;
            CREATE INDEX records_search ON records USING gin (search);
            CREATE INDEX records_title_trgm ON records USING gin (title gin_trgm_ops);
            -- Filter kesetaraan semua field lewat containment (data @> '{"<field_key>": nilai}'),
            -- sepenuhnya ter-binding; satu index GIN melayani semua entity & field.
            CREATE INDEX records_data_gin ON records USING gin (data jsonb_path_ops) WHERE deleted_at IS NULL;

            CREATE TABLE record_links (
                id              uuid PRIMARY KEY DEFAULT uuidv7(),
                relationship_id uuid NOT NULL REFERENCES relationships(id),
                source_id       uuid NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
                target_id       uuid NOT NULL REFERENCES objects(id),
                position        integer NOT NULL DEFAULT 0,
                created_at      timestamptz NOT NULL DEFAULT now(),
                UNIQUE (relationship_id, source_id, target_id)
            );
            CREATE INDEX record_links_target ON record_links (target_id, relationship_id);
            CREATE INDEX record_links_source ON record_links (source_id, relationship_id);

            CREATE TABLE files (
                id             uuid PRIMARY KEY DEFAULT uuidv7(),
                object_id      uuid REFERENCES objects(id),
                field_key      uuid,
                disk           text NOT NULL,
                path           text NOT NULL,
                original_name  text NOT NULL,
                mime_type      text NOT NULL,
                extension      text NOT NULL,
                size_bytes     bigint NOT NULL CHECK (size_bytes >= 0),
                sha256         text NOT NULL CHECK (sha256 ~ '^[0-9a-f]{64}$'),
                scan_status    text NOT NULL DEFAULT 'pending' CHECK (scan_status IN ('pending','clean','infected','error')),
                classification text NOT NULL DEFAULT 'internal',
                uploaded_by    uuid NOT NULL REFERENCES users(id),
                created_at     timestamptz NOT NULL DEFAULT now()
            );
            CREATE INDEX files_object_idx ON files (object_id);

            -- Cast yang ditandai IMMUTABLE agar bisa dipakai index ekspresi (docs/04 §4).
            -- Nilai di JSONB selalu dinormalisasi FieldType::cast (tanggal Y-m-d, waktu ISO-8601 UTC).
            CREATE OR REPLACE FUNCTION bara_to_bigint(text) RETURNS bigint LANGUAGE sql IMMUTABLE PARALLEL SAFE
                AS $$ SELECT $1::bigint $$;
            CREATE OR REPLACE FUNCTION bara_to_numeric(text) RETURNS numeric LANGUAGE sql IMMUTABLE PARALLEL SAFE
                AS $$ SELECT $1::numeric $$;
            CREATE OR REPLACE FUNCTION bara_to_date(text) RETURNS date LANGUAGE sql IMMUTABLE PARALLEL SAFE
                AS $$ SELECT to_date($1, 'YYYY-MM-DD') $$;
            CREATE OR REPLACE FUNCTION bara_to_timestamptz(text) RETURNS timestamptz LANGUAGE sql IMMUTABLE PARALLEL SAFE
                AS $$ SELECT $1::timestamptz $$;
            CREATE OR REPLACE FUNCTION bara_to_boolean(text) RETURNS boolean LANGUAGE sql IMMUTABLE PARALLEL SAFE
                AS $$ SELECT $1::boolean $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS files, record_links, records CASCADE;
            DROP FUNCTION IF EXISTS bara_to_bigint(text), bara_to_numeric(text), bara_to_date(text),
                bara_to_timestamptz(text), bara_to_boolean(text);
            SQL);
    }
};
