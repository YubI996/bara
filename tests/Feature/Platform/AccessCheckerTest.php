<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Organization\Enums\OrganizationKind;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->bidang = createOrganization('bid_p2p', $this->dinkes, OrganizationKind::Bidang);
    $this->dishub = createOrganization('dishub');
});

test('scope dengan turunan mencakup unit di bawahnya, bukan unit lain', function (): void {
    $user = userWithRole('platform_admin', $this->dinkes);
    $access = app(AccessChecker::class);

    expect($access->allows($user, PlatformPermission::OrganizationManage, $this->dinkes->path))->toBeTrue()
        ->and($access->allows($user, PlatformPermission::OrganizationManage, $this->bidang->path))->toBeTrue()
        ->and($access->allows($user, PlatformPermission::OrganizationManage, $this->dishub->path))->toBeFalse()
        ->and($access->allows($user, PlatformPermission::OrganizationManage, rootOrganization()->path))->toBeFalse();
});

test('scope tanpa turunan hanya mencakup unit itu sendiri', function (): void {
    $user = userWithRole('platform_admin', $this->dinkes, includeDescendants: false);

    expect(app(AccessChecker::class)->allows($user, PlatformPermission::OrganizationView, $this->bidang->path))->toBeFalse();
});

test('prefiks path yang mirip tidak dianggap turunan', function (): void {
    $dinkesx = createOrganization('dinkes_x');
    $user = userWithRole('platform_admin', $this->dinkes);

    expect(app(AccessChecker::class)->allows($user, PlatformPermission::OrganizationView, $dinkesx->path))->toBeFalse();
});

test('penugasan kedaluwarsa dan user nonaktif tidak memberi akses', function (): void {
    $expired = userWithRole('platform_admin', $this->dinkes);
    DB::table('role_assignments')->where('user_id', $expired->id)
        ->update(['valid_from' => now()->subDays(10), 'valid_to' => now()->subDay()]);

    $inactive = userWithRole('platform_admin', $this->dinkes);
    $inactive->forceFill(['is_active' => false])->save();

    expect(app(AccessChecker::class)->hasAnywhere($expired, PlatformPermission::OrganizationView))->toBeFalse()
        ->and(app(AccessChecker::class)->hasAnywhere($inactive, PlatformPermission::OrganizationView))->toBeFalse();
});

test('role auditor tidak punya hak kelola', function (): void {
    $auditor = userWithRole('auditor');

    expect(app(AccessChecker::class)->hasAnywhere($auditor, PlatformPermission::OrganizationView))->toBeTrue()
        ->and(app(AccessChecker::class)->hasAnywhere($auditor, PlatformPermission::OrganizationManage))->toBeFalse();
});

test('perintah bara:assign-role memberi role aplikasi dan tercatat di audit', function (): void {
    $app = createApplication('monev');
    $entity = createEntity($app);
    addField($entity, 'nama');
    publishEntity($entity);
    $user = User::factory()->create();

    $this->artisan('bara:assign-role', ['email' => $user->email, 'role' => 'operator', 'org' => 'dinkes', '--app' => 'monev'])->assertSuccessful();

    expect(app(AccessChecker::class)->allows($user, 'monev.kegiatan.create', $this->bidang->path))->toBeTrue()
        ->and(DB::table('audit_logs')->where(['action' => 'role.assigned', 'object_id' => $user->id])->exists())->toBeTrue();
});
