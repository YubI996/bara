<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Access\Contracts\ScopeGrant;
use Illuminate\Database\ConnectionInterface;

/**
 * Evaluasi RBAC + scope organisasi. Hasil di-memo per request (instance scoped).
 */
final class DatabaseAccessChecker implements AccessChecker
{
    /** @var array<string, list<ScopeGrant>> */
    private array $memo = [];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function hasAnywhere(User $user, PlatformPermission|string $permission): bool
    {
        return $this->grants($user, $permission) !== [];
    }

    public function allows(User $user, PlatformPermission|string $permission, string $targetPath): bool
    {
        foreach ($this->grants($user, $permission) as $grant) {
            if ($grant->covers($targetPath)) {
                return true;
            }
        }

        return false;
    }

    public function grants(User $user, PlatformPermission|string $permission): array
    {
        if (! $user->is_active) {
            return [];
        }

        $code = $permission instanceof PlatformPermission ? $permission->value : $permission;
        $key = $user->id.'|'.$code;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $rows = $this->db->table('role_assignments as ra')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'ra.role_id')
            ->join('core_organizations as o', 'o.id', '=', 'ra.scope_org_id')
            ->where('ra.user_id', $user->id)
            ->where('rp.permission_code', $code)
            // Waktu dibandingkan dengan jam database (presisi mikrodetik, satu sumber kebenaran).
            ->whereRaw('ra.valid_from <= now()')
            ->whereRaw('(ra.valid_to IS NULL OR ra.valid_to > now())')
            ->whereRaw('(o.valid_to IS NULL OR o.valid_to > CURRENT_DATE)')
            ->get(['o.path', 'ra.include_descendants']);

        $grants = [];
        foreach ($rows as $row) {
            if (isset($row->path) && is_string($row->path)) {
                $grants[] = new ScopeGrant($row->path, ($row->include_descendants ?? false) === true);
            }
        }

        return $this->memo[$key] = $grants;
    }
}
