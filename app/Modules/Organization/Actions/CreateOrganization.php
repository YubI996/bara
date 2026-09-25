<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\EntityCatalog;
use App\Modules\Organization\Data\CreateOrganizationData;
use App\Modules\Organization\Enums\Sector;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class CreateOrganization
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private EntityCatalog $entities,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(CreateOrganizationData $data): Organization
    {
        return $this->db->transaction(function () use ($data): Organization {
            $parent = Organization::query()->lockForUpdate()->find($data->parentId);

            if ($parent === null || ! $parent->isActive()) {
                throw OrganizationRuleViolation::on('parent_id', 'Unit induk tidak ditemukan atau sudah nonaktif.');
            }

            $path = $parent->path.'.'.$data->code;

            // Organisasi adalah pemilik dirinya sendiri, agar scope pada unit itu mencakup datanya.
            // FK objects.owner_org_id DEFERRABLE, sehingga baru dicek saat commit.
            $id = Str::uuid7()->toString();
            $this->objects->register(
                entityId: $this->entities->entityId('core', 'organization'),
                ownerOrgId: $id,
                ownerPath: $path,
                visibility: Visibility::Internal,
                id: $id,
            );

            $organization = Organization::query()->create([
                'id' => $id,
                'parent_id' => $parent->id,
                'code' => $data->code,
                'name' => $data->name,
                'short_name' => $data->shortName,
                'sector' => Sector::Government,
                'kind' => $data->kind,
                'path' => $path,
                'is_internal' => $parent->is_internal,
            ]);

            $this->audit->log('organization.create', $id, 'core.organization', [
                'code' => [null, $data->code],
                'name' => [null, $data->name],
                'short_name' => [null, $data->shortName],
                'kind' => [null, $data->kind->value],
                'path' => [null, $path],
            ]);

            $this->events->record('organization.created', 'core.organization', $id, [
                'parent_id' => $parent->id,
                'path' => $path,
            ]);

            return $organization;
        });
    }
}
