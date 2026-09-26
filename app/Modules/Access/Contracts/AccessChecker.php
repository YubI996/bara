<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

use App\Models\User;
use App\Shared\Data\DataClassification;

interface AccessChecker
{
    /** Apakah user punya permission ini di mana pun (untuk menampilkan menu). */
    public function hasAnywhere(User $user, PlatformPermission|string $permission): bool;

    /** Apakah user punya permission ini pada organisasi dengan path tersebut. */
    public function allows(User $user, PlatformPermission|string $permission, string $targetPath): bool;

    /**
     * Semua cakupan aktif untuk permission ini (untuk memfilter daftar).
     *
     * @return list<ScopeGrant>
     */
    public function grants(User $user, PlatformPermission|string $permission): array;

    /**
     * Klasifikasi data tertinggi yang boleh dibaca user dalam aplikasi ini: maksimum clearance
     * role aplikasi yang aktif pada unit aktif. Role platform tidak dihitung (ADR 0015).
     * Pemanggil wajib membatasinya ke internal untuk record di luar grant pembaca.
     */
    public function clearance(User $user, string $applicationId): DataClassification;

    /**
     * Wajib 2FA di area data (ADR 0013): pemegang role platform, app_admin, atau role dengan
     * clearance di atas internal yang masih aktif.
     */
    public function requiresTwoFactor(User $user): bool;
}
