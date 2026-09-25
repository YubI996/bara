<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

use App\Models\User;

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
}
