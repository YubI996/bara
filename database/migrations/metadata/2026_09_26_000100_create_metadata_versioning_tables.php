<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Metadata berversi (ADR 0006, docs/04 §3): entity_versions, fields, relationships.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE entity_versions (
                id              uuid PRIMARY KEY DEFAULT uuidv7(),
                entity_id       uuid NOT NULL REFERENCES entities(id),
                version         integer NOT NULL CHECK (version > 0),
                status          text NOT NULL CHECK (status IN ('draft','published','superseded')),
                compiled_schema jsonb,
                change_summary  text,
                published_at    timestamptz,
                published_by    uuid REFERENCES users(id),
                -- Persetujuan Pejabat PDP bila draft memuat field data pribadi (docs/05 §3).
                privacy_reviewed_by uuid REFERENCES users(id),
                privacy_reviewed_at timestamptz,
                created_at      timestamptz NOT NULL DEFAULT now(),
                updated_at      timestamptz NOT NULL DEFAULT now(),
                UNIQUE (entity_id, version),
                CHECK (status = 'draft' OR compiled_schema IS NOT NULL)
            );
            CREATE UNIQUE INDEX entity_versions_one_draft ON entity_versions (entity_id) WHERE status = 'draft';
            CREATE UNIQUE INDEX entity_versions_one_published ON entity_versions (entity_id) WHERE status = 'published';

            -- Versi published/superseded tidak boleh diubah (immutable, ADR 0006).
            CREATE OR REPLACE FUNCTION entity_versions_guard_immutable() RETURNS trigger
                LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status <> 'draft' AND (
                    NEW.compiled_schema IS DISTINCT FROM OLD.compiled_schema
                    OR NEW.version <> OLD.version
                    OR NEW.entity_id <> OLD.entity_id
                    OR (OLD.status = 'superseded' AND NEW.status <> 'superseded')
                ) THEN
                    RAISE EXCEPTION 'entity version % is immutable', OLD.id USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER entity_versions_immutable
                BEFORE UPDATE ON entity_versions
                FOR EACH ROW EXECUTE FUNCTION entity_versions_guard_immutable();

            CREATE TABLE fields (
                id                uuid PRIMARY KEY DEFAULT uuidv7(),
                entity_version_id uuid NOT NULL REFERENCES entity_versions(id) ON DELETE CASCADE,
                field_key         uuid NOT NULL,
                code              text NOT NULL CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                label             text NOT NULL CHECK (length(label) BETWEEN 1 AND 120),
                help_text         text,
                type              text NOT NULL,
                is_required       boolean NOT NULL DEFAULT false,
                is_unique         boolean NOT NULL DEFAULT false,
                is_indexed        boolean NOT NULL DEFAULT false,
                is_searchable     boolean NOT NULL DEFAULT false,
                classification    text NOT NULL DEFAULT 'internal'
                                  CHECK (classification IN ('public','internal','restricted','personal','personal_specific')),
                config            jsonb NOT NULL DEFAULT '{}',
                position          integer NOT NULL,
                created_at        timestamptz NOT NULL DEFAULT now(),
                updated_at        timestamptz NOT NULL DEFAULT now(),
                UNIQUE (entity_version_id, code),
                UNIQUE (entity_version_id, field_key),
                CHECK (NOT is_unique OR is_indexed)
            );
            CREATE INDEX fields_version_position_idx ON fields (entity_version_id, position);

            CREATE TABLE relationships (
                id               uuid PRIMARY KEY DEFAULT uuidv7(),
                field_key        uuid NOT NULL,
                source_entity_id uuid NOT NULL REFERENCES entities(id),
                target_entity_id uuid NOT NULL REFERENCES entities(id),
                code             text NOT NULL CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                cardinality      text NOT NULL CHECK (cardinality IN ('many_to_one','many_to_many')),
                inverse_code     text CHECK (inverse_code IS NULL OR inverse_code ~ '^[a-z][a-z0-9_]{1,62}$'),
                on_target_delete text NOT NULL DEFAULT 'restrict' CHECK (on_target_delete IN ('restrict','nullify')),
                is_active        boolean NOT NULL DEFAULT true,
                created_at       timestamptz NOT NULL DEFAULT now(),
                updated_at       timestamptz NOT NULL DEFAULT now(),
                UNIQUE (source_entity_id, field_key)
            );
            CREATE UNIQUE INDEX relationships_active_code ON relationships (source_entity_id, code) WHERE is_active;
            CREATE INDEX relationships_target_idx ON relationships (target_entity_id) WHERE is_active;

            ALTER TABLE entities
                ADD COLUMN published_version_id uuid REFERENCES entity_versions(id),
                ADD COLUMN draft_version_id uuid REFERENCES entity_versions(id),
                ADD COLUMN description text;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE entities DROP COLUMN IF EXISTS published_version_id,
                DROP COLUMN IF EXISTS draft_version_id, DROP COLUMN IF EXISTS description;
            DROP TABLE IF EXISTS relationships, fields, entity_versions CASCADE;
            DROP FUNCTION IF EXISTS entity_versions_guard_immutable();
            SQL);
    }
};
