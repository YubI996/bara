<?php

declare(strict_types=1);

return [
    /*
    | Identitas instance Pemda (ADR 0012: satu instance per Pemda).
    | region_code: kode wilayah Kemendagri, dipakai sebagai prefiks URN global.
    */
    'pemda' => [
        'code' => env('BARA_PEMDA_CODE', 'pemda'),
        'name' => env('BARA_PEMDA_NAME', 'Pemerintah Daerah'),
        'region_code' => env('BARA_REGION_CODE'),
        // Zona waktu tampilan. Penyimpanan tetap UTC (timestamptz).
        'timezone' => env('BARA_TIMEZONE', 'Asia/Jakarta'),
    ],

    // Administrator pertama untuk PlatformBootstrapSeeder. Password kosong = dibuat acak.
    'admin' => [
        'email' => env('BARA_ADMIN_EMAIL', 'admin@example.test'),
        'password' => env('BARA_ADMIN_PASSWORD', ''),
    ],

    'records' => [
        // CREATE INDEX CONCURRENTLY tidak bisa dalam transaksi; test memakai false.
        'concurrent_index' => filter_var(env('BARA_CONCURRENT_INDEX', true), FILTER_VALIDATE_BOOLEAN),
        'page_size' => 25,
    ],

    'security' => [
        // docs/05 §6: idle timeout pengguna ber-role berisiko tinggi (admin/platform/clearance tinggi).
        'privileged_idle_minutes' => (int) env('BARA_PRIVILEGED_IDLE_MINUTES', 30),
    ],

    'files' => [
        'disk' => env('BARA_FILES_DISK', 'local'),
        // none = tanpa pemindai (hanya dev/test); clamav = pindai sebelum boleh diunduh.
        // Default produksi fail-closed: clamav (SEC-009).
        'scanner' => env('BARA_FILE_SCANNER', env('APP_ENV') === 'production' ? 'clamav' : 'none'),
        'clamav' => [
            // tcp://127.0.0.1:3310 atau unix:///var/run/clamav/clamd.ctl
            'socket' => env('BARA_CLAMAV_SOCKET', 'tcp://127.0.0.1:3310'),
            'timeout' => (float) env('BARA_CLAMAV_TIMEOUT', 30),
        ],
    ],
];
