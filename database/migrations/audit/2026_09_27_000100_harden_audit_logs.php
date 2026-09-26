<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0011: TRUNCATE juga ditolak (trigger per statement, termasuk partisi yang ada).
 * DETACH/DROP partisi dan DISABLE TRIGGER hanya bisa dicegah dengan pemisahan role DB
 * (aplikasi bukan owner tabel), lihat docs/13 §Deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_block_truncate() RETURNS trigger
                LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only (TRUNCATE blocked)'
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER audit_logs_no_truncate
                BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_block_truncate();
        SQL);

        // Trigger statement pada tabel induk tidak berlaku saat partisi di-TRUNCATE langsung.
        $partitions = DB::select("SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent WHERE p.relname = 'audit_logs'");
        foreach ($partitions as $partition) {
            $name = is_object($partition) && is_string($partition->relname ?? null) ? $partition->relname : null;
            if ($name !== null && preg_match('/^audit_logs_[a-z0-9_]+$/', $name) === 1) {
                DB::statement("CREATE TRIGGER {$name}_no_truncate BEFORE TRUNCATE ON {$name} FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_block_truncate()");
            }
        }
    }

    public function down(): void
    {
        $partitions = DB::select("SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent WHERE p.relname = 'audit_logs'");
        foreach ($partitions as $partition) {
            $name = is_object($partition) && is_string($partition->relname ?? null) ? $partition->relname : null;
            if ($name !== null && preg_match('/^audit_logs_[a-z0-9_]+$/', $name) === 1) {
                DB::statement("DROP TRIGGER IF EXISTS {$name}_no_truncate ON {$name}");
            }
        }

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs; DROP FUNCTION IF EXISTS audit_logs_block_truncate();');
    }
};
