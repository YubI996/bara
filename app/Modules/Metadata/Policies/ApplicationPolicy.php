<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Policies;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Metadata\Models\Application;
use App\Modules\Organization\Contracts\OrganizationDirectory;

/**
 * Hak atas metadata ditentukan scope organisasi pemilik aplikasi (ADR 0007).
 */
final class ApplicationPolicy
{
    /** @var array<string, string|null> */
    private array $paths = [];

    public function __construct(
        private readonly AccessChecker $access,
        private readonly OrganizationDirectory $organizations,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasAnywhere($user, PlatformPermission::MetadataManage)
            || $this->access->hasAnywhere($user, PlatformPermission::MetadataPublish)
            || $this->access->hasAnywhere($user, PlatformPermission::PrivacyReview);
    }

    public function view(User $user, Application $application): bool
    {
        return $this->can($user, $application, PlatformPermission::MetadataManage)
            || $this->can($user, $application, PlatformPermission::MetadataPublish)
            || $this->can($user, $application, PlatformPermission::PrivacyReview);
    }

    public function create(User $user): bool
    {
        return $this->access->hasAnywhere($user, PlatformPermission::MetadataManage);
    }

    public function update(User $user, Application $application): bool
    {
        return ! $application->is_system && $this->can($user, $application, PlatformPermission::MetadataManage);
    }

    public function publish(User $user, Application $application): bool
    {
        return ! $application->is_system && $this->can($user, $application, PlatformPermission::MetadataPublish);
    }

    public function reviewPrivacy(User $user, Application $application): bool
    {
        return $this->can($user, $application, PlatformPermission::PrivacyReview);
    }

    private function can(User $user, Application $application, PlatformPermission $permission): bool
    {
        $path = $this->paths[$application->owner_org_id] ??= $this->organizations->find($application->owner_org_id)?->path;

        return $path !== null && $this->access->allows($user, $permission, $path);
    }
}
