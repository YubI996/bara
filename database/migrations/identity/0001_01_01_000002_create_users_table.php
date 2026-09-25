<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (
                id                        uuid PRIMARY KEY DEFAULT uuidv7(),
                name                      text NOT NULL,
                email                     citext NOT NULL UNIQUE,
                email_verified_at         timestamptz,
                password                  text,
                kind                      text NOT NULL DEFAULT 'internal'
                                          CHECK (kind IN ('internal','external','service')),
                primary_org_id            uuid NOT NULL REFERENCES core_organizations(id),
                is_active                 boolean NOT NULL DEFAULT true,
                last_login_at             timestamptz,
                two_factor_secret         text,
                two_factor_recovery_codes text,
                two_factor_confirmed_at   timestamptz,
                remember_token            varchar(100),
                created_at                timestamptz,
                updated_at                timestamptz
            );
            CREATE INDEX users_primary_org_idx ON users (primary_org_id);

            CREATE TABLE password_reset_tokens (
                email      citext PRIMARY KEY,
                token      text NOT NULL,
                created_at timestamptz
            );

            CREATE TABLE sessions (
                id            varchar(255) PRIMARY KEY,
                user_id       uuid REFERENCES users(id) ON DELETE CASCADE,
                ip_address    varchar(45),
                user_agent    text,
                payload       text NOT NULL,
                last_activity integer NOT NULL
            );
            CREATE INDEX sessions_user_id_idx ON sessions (user_id);
            CREATE INDEX sessions_last_activity_idx ON sessions (last_activity);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS sessions, password_reset_tokens, users CASCADE');
    }
};
