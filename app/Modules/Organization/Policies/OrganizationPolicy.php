<?php

declare(strict_types=1);

namespace App\Modules\Organization\Policies;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Organization\Models\Organization;

final readonly class OrganizationPolicy
{
    public function __construct(private AccessChecker $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasAnywhere($user, PlatformPermission::OrganizationView);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, PlatformPermission::OrganizationView, $organization->path);
    }

    /** Menampilkan tombol/form tambah; unit induk dicek lagi saat simpan. */
    public function create(User $user): bool
    {
        return $this->access->hasAnywhere($user, PlatformPermission::OrganizationManage);
    }

    public function createUnder(User $user, Organization $parent): bool
    {
        return $this->access->allows($user, PlatformPermission::OrganizationManage, $parent->path);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, PlatformPermission::OrganizationManage, $organization->path);
    }

    public function deactivate(User $user, Organization $organization): bool
    {
        return ! $organization->isRoot()
            && $this->access->allows($user, PlatformPermission::OrganizationManage, $organization->path);
    }
}
