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
];
