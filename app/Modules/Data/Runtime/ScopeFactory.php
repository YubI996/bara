<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Metadata\Contracts\EntitySchema;

final readonly class ScopeFactory
{
    public function __construct(private AccessChecker $access) {}

    public function read(User $user, EntitySchema $schema): AccessScope
    {
        return AccessScope::read($this->access->grants($user, $schema->permission('view')), $user->kind === 'internal');
    }

    /** @param  'create'|'update'|'delete'|'export'  $action */
    public function write(User $user, EntitySchema $schema, string $action): AccessScope
    {
        return AccessScope::write($this->access->grants($user, $schema->permission($action)));
    }

    public function canView(User $user, EntitySchema $schema): bool
    {
        return $this->access->hasAnywhere($user, $schema->permission('view'));
    }

    public function can(User $user, EntitySchema $schema, string $action): bool
    {
        return $this->access->hasAnywhere($user, $schema->permission($action));
    }
}
