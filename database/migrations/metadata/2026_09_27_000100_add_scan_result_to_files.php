<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasil pindai lampiran (docs/04 §4): waktu pindai dan nama signature/alasan gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE files
                ADD COLUMN scanned_at  timestamptz,
                ADD COLUMN scan_detail text;
            CREATE INDEX files_pending_idx ON files (created_at) WHERE scan_status = 'pending';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS files_pending_idx;
            ALTER TABLE files DROP COLUMN IF EXISTS scan_detail, DROP COLUMN IF EXISTS scanned_at;
        SQL);
    }
};
