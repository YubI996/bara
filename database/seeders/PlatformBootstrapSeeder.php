<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Access\Contracts\PlatformPermission;
use App\Shared\Validation\Identifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bootstrap platform dalam SATU transaksi (docs/04 §2): organisasi akar Pemda, aplikasi `core`,
 * entity Core, permission & role bawaan, dan administrator pertama. Idempotent.
 */
final class PlatformBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('applications')->where('code', 'core')->exists()) {
            $this->command->info('Platform sudah di-bootstrap, dilewati.');

            return;
        }

        $rootCode = config()->string('bara.pemda.code');
        $rootName = config()->string('bara.pemda.name');
        $adminEmail = config()->string('bara.admin.email');
        $adminPassword = config()->string('bara.admin.password');

        if (! Identifier::isValid($rootCode)) {
            throw new RuntimeException("BARA_PEMDA_CODE [{$rootCode}] tidak valid (huruf kecil, angka, underscore).");
        }

        $generatedPassword = null;
        if ($adminPassword === '') {
            $generatedPassword = $adminPassword = Str::password(20);
        }

        DB::transaction(function () use ($rootCode, $rootName, $adminEmail, $adminPassword): void {
            $rootId = Str::uuid7()->toString();
            $coreAppId = Str::uuid7()->toString();
            $orgEntityId = Str::uuid7()->toString();

            DB::table('applications')->insert([
                'id' => $coreAppId,
                'code' => 'core',
                'name' => 'Core Platform',
                'description' => 'Master data bersama: organisasi, wilayah, orang, pegawai, tahun anggaran.',
                'owner_org_id' => $rootId,
                'status' => 'active',
                'is_system' => true,
            ]);

            DB::table('entities')->insert([
                'id' => $orgEntityId,
                'application_id' => $coreAppId,
                'code' => 'organization',
                'name' => 'Organisasi',
                'name_plural' => 'Organisasi',
                'storage_type' => 'physical',
                'physical_table' => 'core_organizations',
                'is_system' => true,
                'is_shared' => true,
                'default_visibility' => 'internal',
            ]);

            DB::table('objects')->insert([
                'id' => $rootId,
                'entity_id' => $orgEntityId,
                'owner_org_id' => $rootId,
                'owner_path' => $rootCode,
                'visibility' => 'internal',
            ]);

            DB::table('core_organizations')->insert([
                'id' => $rootId,
                'parent_id' => null,
                'code' => $rootCode,
                'name' => $rootName,
                'sector' => 'government',
                'kind' => 'pemda',
                'path' => $rootCode,
                'is_internal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (PlatformPermission::cases() as $permission) {
                DB::table('permissions')->insert([
                    'code' => $permission->value,
                    'description' => $permission->description(),
                ]);
            }

            $adminRoleId = $this->createSystemRole('platform_admin', 'Administrator Platform', PlatformPermission::cases());
            $this->createSystemRole('auditor', 'Auditor', [PlatformPermission::OrganizationView, PlatformPermission::AuditView]);

            $adminId = Str::uuid7()->toString();
            DB::table('users')->insert([
                'id' => $adminId,
                'name' => 'Administrator Platform',
                'email' => $adminEmail,
                'email_verified_at' => now(),
                'password' => Hash::make($adminPassword),
                'kind' => 'internal',
                'primary_org_id' => $rootId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_assignments')->insert([
                'user_id' => $adminId,
                'role_id' => $adminRoleId,
                'scope_org_id' => $rootId,
                'include_descendants' => true,
            ]);

            DB::table('audit_logs')->insert([
                'actor_type' => 'system',
                'action' => 'platform.bootstrap',
                'object_id' => $rootId,
                'object_type' => 'core.organization',
                'context' => json_encode(['admin_user_id' => $adminId], JSON_THROW_ON_ERROR),
            ]);
        });

        $this->command->info("Platform di-bootstrap. Admin: {$adminEmail}");

        if ($generatedPassword !== null) {
            $this->command->warn("Password admin dibuat acak (tampil SEKALI): {$generatedPassword}");
            $this->command->warn('Segera login, ganti password, dan aktifkan 2FA.');
        }
    }

    /** @param  list<PlatformPermission>  $permissions */
    private function createSystemRole(string $code, string $name, array $permissions): string
    {
        $id = Str::uuid7()->toString();

        DB::table('roles')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'clearance' => 'restricted',
            'is_system' => true,
        ]);

        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $id,
                'permission_code' => $permission->value,
            ]);
        }

        return $id;
    }
}
