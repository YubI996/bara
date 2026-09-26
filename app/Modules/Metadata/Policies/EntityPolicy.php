<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Policies;

use App\Models\User;
use App\Modules\Metadata\Models\Entity;

/** Hak atas entity = hak atas aplikasinya; entity sistem tidak bisa diubah lewat UI. */
final readonly class EntityPolicy
{
    public function __construct(private ApplicationPolicy $applications) {}

    public function view(User $user, Entity $entity): bool
    {
        return $this->applications->view($user, $entity->application);
    }

    public function update(User $user, Entity $entity): bool
    {
        return ! $entity->is_system && $this->applications->update($user, $entity->application);
    }

    public function publish(User $user, Entity $entity): bool
    {
        return ! $entity->is_system && $this->applications->publish($user, $entity->application);
    }

    public function reviewPrivacy(User $user, Entity $entity): bool
    {
        return $this->applications->reviewPrivacy($user, $entity->application);
    }
}
