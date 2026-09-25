<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fondasi object registry (ADR 0005) dan metadata minimum untuk bootstrap.
 *
 * Rantai FK melingkar: applications -> core_organizations -> objects -> entities -> applications.
 * Karena itu FK penutup lingkaran dibuat DEFERRABLE INITIALLY DEFERRED (docs/04 §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE applications (
                id           uuid PRIMARY KEY DEFAULT uuidv7(),
                code         text NOT NULL UNIQUE CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                name         text NOT NULL,
                description  text,
                owner_org_id uuid NOT NULL,
                status       text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','active','archived')),
                is_system    boolean NOT NULL DEFAULT false,
                created_at   timestamptz NOT NULL DEFAULT now(),
                updated_at   timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE entities (
                id                 uuid PRIMARY KEY DEFAULT uuidv7(),
                application_id     uuid NOT NULL REFERENCES applications(id),
                code               text NOT NULL CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                name               text NOT NULL,
                name_plural        text NOT NULL,
                storage_type       text NOT NULL CHECK (storage_type IN ('physical','document')),
                physical_table     text,
                is_system          boolean NOT NULL DEFAULT false,
                is_shared          boolean NOT NULL DEFAULT false,
                default_visibility text NOT NULL DEFAULT 'private'
                                   CHECK (default_visibility IN ('private','internal','partner','public')),
                title_template     text NOT NULL DEFAULT '{name}',
                created_at         timestamptz NOT NULL DEFAULT now(),
                updated_at         timestamptz NOT NULL DEFAULT now(),
                UNIQUE (application_id, code),
                CHECK (storage_type = 'document' OR physical_table IS NOT NULL)
            );

            CREATE TABLE objects (
                id           uuid PRIMARY KEY DEFAULT uuidv7(),
                entity_id    uuid NOT NULL,
                owner_org_id uuid NOT NULL,
                owner_path   ltree NOT NULL,
                visibility   text NOT NULL DEFAULT 'private'
                             CHECK (visibility IN ('private','internal','partner','public')),
                created_at   timestamptz NOT NULL DEFAULT now(),
                deleted_at   timestamptz
            );
            CREATE INDEX objects_owner_path_gist ON objects USING gist (owner_path);
            CREATE INDEX objects_entity_idx ON objects (entity_id) WHERE deleted_at IS NULL;

            CREATE TABLE core_organizations (
                id          uuid PRIMARY KEY REFERENCES objects(id),
                parent_id   uuid REFERENCES core_organizations(id),
                code        text NOT NULL UNIQUE CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                name        text NOT NULL,
                short_name  text,
                sector      text NOT NULL CHECK (sector IN ('government','academia','business','community','media')),
                kind        text NOT NULL,
                path        ltree NOT NULL UNIQUE,
                is_internal boolean NOT NULL,
                verified_at timestamptz,
                valid_from  date NOT NULL DEFAULT CURRENT_DATE,
                valid_to    date,
                created_at  timestamptz NOT NULL DEFAULT now(),
                updated_at  timestamptz NOT NULL DEFAULT now(),
                CHECK (parent_id IS NULL OR parent_id <> id),
                CHECK (valid_to IS NULL OR valid_to >= valid_from)
            );
            CREATE INDEX core_org_path_gist ON core_organizations USING gist (path);
            CREATE INDEX core_org_parent_idx ON core_organizations (parent_id);

            ALTER TABLE objects
                ADD CONSTRAINT objects_entity_fk FOREIGN KEY (entity_id)
                    REFERENCES entities(id) DEFERRABLE INITIALLY DEFERRED,
                ADD CONSTRAINT objects_owner_org_fk FOREIGN KEY (owner_org_id)
                    REFERENCES core_organizations(id) DEFERRABLE INITIALLY DEFERRED;

            ALTER TABLE applications
                ADD CONSTRAINT applications_owner_org_fk FOREIGN KEY (owner_org_id)
                    REFERENCES core_organizations(id) DEFERRABLE INITIALLY DEFERRED;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS core_organizations, objects, entities, applications CASCADE;
            SQL);
    }
};
