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

    /**
     * Memastikan role bawaan aplikasi (app_admin, operator, viewer) ada dan memegang permission
     * entity yang baru terbit. Clearance role aplikasi = internal; data pribadi butuh role khusus.
     *
     * @param  list<string>  $actions  aksi entity, mis. ['view','create','update','delete','export']
     */
    public function grantEntityToApplicationRoles(string $applicationId, string $applicationCode, string $entityCode, array $actions): void;

    /** Menyinkronkan permission platform dan isi role bawaan (idempotent). */
    public function syncSystemRoles(): void;
}
