<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure;

use App\Modules\Access\Contracts\PermissionRegistry;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Access\Contracts\SystemRole;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabasePermissionRegistry implements PermissionRegistry
{
    private const string CODE_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){2}$/';

    public function __construct(private ConnectionInterface $db) {}

    public function register(array $permissions): void
    {
        foreach ($permissions as $code => $description) {
            if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                throw new InvalidArgumentException("Kode permission [{$code}] tidak valid.");
            }

            $this->db->table('permissions')->upsert(
                [['code' => $code, 'description' => $description]],
                ['code'],
                ['description'],
            );
        }
    }

    /** @var array<string, array{string, list<string>}> role aplikasi => [nama, aksi] */
    private const array APPLICATION_ROLES = [
        'app_admin' => ['Admin aplikasi', ['view', 'create', 'update', 'delete', 'export']],
        'operator' => ['Operator', ['view', 'create', 'update', 'delete']],
        'viewer' => ['Pembaca', ['view']],
    ];

    public function grantEntityToApplicationRoles(string $applicationId, string $applicationCode, string $entityCode, array $actions): void
    {
        foreach (self::APPLICATION_ROLES as $code => [$name, $roleActions]) {
            $roleId = $this->db->table('roles')->where('application_id', $applicationId)->where('code', $code)->value('id');

            if (! is_string($roleId)) {
                $roleId = Str::uuid7()->toString();
                $this->db->table('roles')->insert([
                    'id' => $roleId,
                    'code' => $code,
                    'application_id' => $applicationId,
                    'name' => $name,
                    'clearance' => 'internal',
                    'is_system' => true,
                ]);
            }

            $rows = [];
            foreach (array_intersect($roleActions, $actions) as $action) {
                $rows[] = ['role_id' => $roleId, 'permission_code' => "{$applicationCode}.{$entityCode}.{$action}"];
            }
            $this->db->table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function syncSystemRoles(): void
    {
        $this->db->transaction(function (): void {
            $all = [];
            foreach (PlatformPermission::cases() as $permission) {
                $all[$permission->value] = $permission->description();
            }
            $this->register($all);

            foreach (SystemRole::cases() as $role) {
                $roleId = $this->db->table('roles')
                    ->where('code', $role->value)->whereNull('application_id')->value('id');

                if (! is_string($roleId)) {
                    $roleId = Str::uuid7()->toString();
                    $this->db->table('roles')->insert([
                        'id' => $roleId,
                        'code' => $role->value,
                        'name' => $role->label(),
                        'clearance' => $role->clearance()->value,
                        'is_system' => true,
                    ]);
                }

                $wanted = array_map(static fn (PlatformPermission $p): string => $p->value, $role->permissions());

                // Role bawaan dikelola kode: permission platform di luar daftar dicabut.
                $this->db->table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_code', 'like', 'platform.%')
                    ->whereNotIn('permission_code', $wanted)
                    ->delete();

                $this->db->table('role_permissions')->insertOrIgnore(array_map(
                    static fn (string $code): array => ['role_id' => $roleId, 'permission_code' => $code],
                    $wanted,
                ));
            }
        });
    }
}
