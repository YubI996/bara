<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganization;
use App\Modules\Organization\Data\CreateOrganizationData;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

function rootOrganization(): Organization
{
    return Organization::query()->whereNull('parent_id')->firstOrFail();
}

function createOrganization(string $code, ?Organization $parent = null, OrganizationKind $kind = OrganizationKind::Dinas): Organization
{
    return app(CreateOrganization::class)->execute(new CreateOrganizationData(
        parentId: ($parent ?? rootOrganization())->id,
        code: $code,
        name: 'Unit '.$code,
        shortName: null,
        kind: $kind,
    ));
}

/** User dengan role pada scope organisasi tertentu; 2FA aktif secara default. */
function userWithRole(string $roleCode, ?Organization $scope = null, bool $includeDescendants = true, bool $twoFactor = true): User
{
    $factory = User::factory();
    $user = ($twoFactor ? $factory->withTwoFactor() : $factory)->create([
        'primary_org_id' => ($scope ?? rootOrganization())->id,
    ]);

    DB::table('role_assignments')->insert([
        'user_id' => $user->id,
        'role_id' => DB::table('roles')->where('code', $roleCode)->whereNull('application_id')->value('id'),
        'scope_org_id' => ($scope ?? rootOrganization())->id,
        'include_descendants' => $includeDescendants,
    ]);

    return $user;
}
