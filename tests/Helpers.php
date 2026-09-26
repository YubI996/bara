<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Metadata\Actions\CreateApplication;
use App\Modules\Metadata\Actions\CreateEntity;
use App\Modules\Metadata\Actions\PublishEntityVersion;
use App\Modules\Metadata\Actions\SaveDraftField;
use App\Modules\Metadata\Data\ApplicationData;
use App\Modules\Metadata\Data\EntityData;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Models\Field;
use App\Modules\Organization\Actions\CreateOrganization;
use App\Modules\Organization\Data\CreateOrganizationData;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use App\Shared\Data\DataClassification;
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

function createApplication(string $code = 'monev', ?Organization $owner = null): Application
{
    return app(CreateApplication::class)->execute(new ApplicationData(
        name: 'Aplikasi '.$code,
        description: null,
        ownerOrgId: ($owner ?? rootOrganization())->id,
        status: 'active',
        code: $code,
    ));
}

function createEntity(Application $application, string $code = 'kegiatan', bool $shared = false, string $titleTemplate = ''): Entity
{
    return app(CreateEntity::class)->execute($application, new EntityData(
        name: ucfirst($code),
        namePlural: ucfirst($code),
        description: null,
        defaultVisibility: 'internal',
        isShared: $shared,
        titleTemplate: $titleTemplate,
        code: $code,
    ));
}

/** @param  array<string, mixed>  $config */
function addField(
    Entity $entity,
    string $code,
    string $type = 'string',
    array $config = [],
    bool $required = false,
    DataClassification $classification = DataClassification::Internal,
    ?string $label = null,
    bool $indexed = false,
): Field {
    return app(SaveDraftField::class)->execute($entity->refresh(), new FieldInput(
        code: $code,
        label: $label ?? 'Label '.$code,
        helpText: null,
        type: $type,
        required: $required,
        unique: false,
        indexed: $indexed,
        searchable: false,
        classification: $classification,
        config: $config,
    ));
}

function publishEntity(Entity $entity, ?User $publisher = null): EntityVersion
{
    return app(PublishEntityVersion::class)
        ->execute($entity->refresh(), $publisher ?? userWithRole('platform_admin'));
}

/** User dengan role aplikasi (app_admin/operator/viewer) pada scope unit tertentu. */
function userWithAppRole(Application $application, string $roleCode, ?Organization $scope = null, bool $includeDescendants = true): User
{
    $user = User::factory()->create(['primary_org_id' => ($scope ?? rootOrganization())->id]);
    $roleId = DB::table('roles')->where('application_id', $application->id)->where('code', $roleCode)->value('id');

    if (! is_string($roleId)) {
        throw new RuntimeException("Role {$roleCode} belum ada; publikasikan entity dulu.");
    }

    DB::table('role_assignments')->insert([
        'user_id' => $user->id,
        'role_id' => $roleId,
        'scope_org_id' => ($scope ?? rootOrganization())->id,
        'include_descendants' => $includeDescendants,
    ]);

    return $user;
}
