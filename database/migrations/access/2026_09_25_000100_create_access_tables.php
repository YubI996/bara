<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Otorisasi RBAC + scope organisasi (ADR 0007, docs/04 §6). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE roles (
                id             uuid PRIMARY KEY DEFAULT uuidv7(),
                code           text NOT NULL CHECK (code ~ '^[a-z][a-z0-9_]{1,62}$'),
                application_id uuid REFERENCES applications(id),
                name           text NOT NULL,
                clearance      text NOT NULL DEFAULT 'internal'
                               CHECK (clearance IN ('public','internal','restricted','personal','personal_specific')),
                is_system      boolean NOT NULL DEFAULT false,
                created_at     timestamptz NOT NULL DEFAULT now(),
                updated_at     timestamptz NOT NULL DEFAULT now(),
                UNIQUE NULLS NOT DISTINCT (application_id, code)
            );

            CREATE TABLE permissions (
                code        text PRIMARY KEY CHECK (code ~ '^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){2}$'),
                description text NOT NULL
            );

            CREATE TABLE role_permissions (
                role_id         uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
                permission_code text NOT NULL REFERENCES permissions(code),
                PRIMARY KEY (role_id, permission_code)
            );

            CREATE TABLE role_assignments (
                id                  uuid PRIMARY KEY DEFAULT uuidv7(),
                user_id             uuid NOT NULL REFERENCES users(id),
                role_id             uuid NOT NULL REFERENCES roles(id),
                scope_org_id        uuid NOT NULL REFERENCES core_organizations(id),
                include_descendants boolean NOT NULL DEFAULT true,
                valid_from          timestamptz NOT NULL DEFAULT now(),
                valid_to            timestamptz,
                granted_by          uuid REFERENCES users(id),
                created_at          timestamptz NOT NULL DEFAULT now(),
                UNIQUE (user_id, role_id, scope_org_id),
                CHECK (valid_to IS NULL OR valid_to > valid_from)
            );
            CREATE INDEX role_assignments_user_idx ON role_assignments (user_id);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS role_assignments, role_permissions, permissions, roles CASCADE');
    }
};
