<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit log append-only, dipartisi per bulan (ADR 0011).
 * Partisi DEFAULT menjamin insert tidak pernah gagal; partisi bulanan dibuat oleh
 * perintah `bara:audit-partitions` (dijadwalkan) beberapa bulan ke depan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE audit_logs (
                id          uuid NOT NULL DEFAULT uuidv7(),
                occurred_at timestamptz NOT NULL DEFAULT now(),
                actor_type  text NOT NULL CHECK (actor_type IN ('user','api_client','system')),
                actor_id    uuid,
                action      text NOT NULL,
                object_id   uuid,
                object_type text,
                changes     jsonb,
                context     jsonb,
                ip          inet,
                user_agent  text,
                trace_id    text,
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at);

            CREATE TABLE audit_logs_default PARTITION OF audit_logs DEFAULT;

            CREATE INDEX audit_logs_object_idx ON audit_logs (object_id, occurred_at DESC);
            CREATE INDEX audit_logs_actor_idx ON audit_logs (actor_id, occurred_at DESC);
            CREATE INDEX audit_logs_action_idx ON audit_logs (action, occurred_at DESC);

            CREATE OR REPLACE FUNCTION audit_logs_block_mutation() RETURNS trigger
                LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only (% blocked)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_block_mutation();
            SQL);

        // Partisi bulan berjalan + 3 bulan ke depan, agar partisi DEFAULT hampir tidak pernah terisi.
        $month = CarbonImmutable::now('UTC')->startOfMonth();
        for ($i = 0; $i <= 3; $i++) {
            $from = $month->addMonths($i);
            DB::statement(sprintf(
                "CREATE TABLE IF NOT EXISTS audit_logs_%s PARTITION OF audit_logs FOR VALUES FROM ('%s') TO ('%s')",
                $from->format('Y_m'),
                $from->toDateTimeString(),
                $from->addMonth()->toDateTimeString(),
            ));
        }
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS audit_logs CASCADE;
            DROP FUNCTION IF EXISTS audit_logs_block_mutation();
            SQL);
    }
};
