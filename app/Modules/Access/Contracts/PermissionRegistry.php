<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

interface PermissionRegistry
{
    /**
     * Mendaftarkan permission (idempotent). Kode: `{app}.{entity}.{aksi}` atau `platform.{area}.{aksi}`.
     *
     * @param  array<string, string>  $permissions  kode => deskripsi
     */
    public function register(array $permissions): void;

    /** Menyinkronkan permission platform dan isi role bawaan (idempotent). */
    public function syncSystemRoles(): void;
}
