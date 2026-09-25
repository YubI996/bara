<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ltree: hierarki organisasi & scope akses (ADR 0007)
        // pg_trgm: pencarian judul record; citext: email case-insensitive
        // pgcrypto: digest/hmac di sisi DB bila dibutuhkan
        foreach (['ltree', 'pg_trgm', 'citext', 'pgcrypto'] as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }
    }

    public function down(): void
    {
        // Extension sengaja tidak di-drop: bisa dipakai objek lain di database yang sama.
    }
};
