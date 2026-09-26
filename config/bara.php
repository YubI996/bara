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

    'files' => [
        'disk' => env('BARA_FILES_DISK', 'local'),
        // none = belum ada pemindai (dev); clamav = pindai sebelum boleh diunduh (produksi).
        'scanner' => env('BARA_FILE_SCANNER', 'none'),
    ],
];
