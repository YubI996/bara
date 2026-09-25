<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Organization\Data\UpdateOrganizationData;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\ConnectionInterface;

/**
 * Mengubah atribut organisasi, termasuk memindahkan ke induk lain (restrukturisasi).
 * Pemindahan menulis ulang path semua unit turunan dan owner_path semua objeknya.
 */
final readonly class UpdateOrganization
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(Organization $organization, UpdateOrganizationData $data): Organization
    {
        return $this->db->transaction(function () use ($organization, $data): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $oldPath = $organization->path;
            $newPath = $oldPath;
            $moved = false;

            if ($organization->isRoot()) {
                if ($data->parentId !== null) {
                    throw OrganizationRuleViolation::on('parent_id', 'Unit akar Pemda tidak dapat dipindahkan.');
                }
            } elseif ($data->parentId !== $organization->parent_id) {
                $newParent = $data->parentId === null ? null : Organization::query()->lockForUpdate()->find($data->parentId);

                if ($newParent === null || ! $newParent->isActive()) {
                    throw OrganizationRuleViolation::on('parent_id', 'Unit induk tidak ditemukan atau sudah nonaktif.');
                }

                if ($newParent->id === $organization->id || str_starts_with($newParent->path.'.', $oldPath.'.')) {
                    throw OrganizationRuleViolation::on('parent_id', 'Unit tidak dapat dipindahkan ke dirinya sendiri atau ke unit di bawahnya.');
                }

                $newPath = $newParent->path.'.'.$organization->code;
                $organization->parent_id = $newParent->id;
                $moved = true;
            }

            $before = [
                'parent_id' => $organization->getOriginal('parent_id'),
                'name' => $organization->name,
                'short_name' => $organization->short_name,
                'kind' => $organization->kind->value,
                'path' => $oldPath,
            ];

            $organization->name = $data->name;
            $organization->short_name = $data->shortName;
            $organization->kind = $data->kind;
            $organization->save();

            if ($moved) {
                $this->db->update(
                    'UPDATE core_organizations SET path = CASE WHEN path = ?::ltree THEN ?::ltree ELSE ?::ltree || subpath(path, nlevel(?::ltree)) END, updated_at = now() WHERE path <@ ?::ltree',
                    [$oldPath, $newPath, $newPath, $oldPath, $oldPath],
                );
                $this->objects->rebaseOwnerPaths($oldPath, $newPath);
            }

            $after = [
                'parent_id' => $organization->parent_id,
                'name' => $organization->name,
                'short_name' => $organization->short_name,
                'kind' => $organization->kind->value,
                'path' => $newPath,
            ];

            $changes = [];
            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = [$before[$field], $value];
                }
            }

            if ($changes === []) {
                return $organization->refresh();
            }

            $this->audit->log('organization.update', $organization->id, 'core.organization', $changes);
            $this->events->record('organization.updated', 'core.organization', $organization->id, [
                'changed_fields' => array_keys($changes),
            ]);

            if ($moved) {
                $this->events->record('organization.restructured', 'core.organization', $organization->id, [
                    'old_path' => $oldPath,
                    'new_path' => $newPath,
                ]);
            }

            return $organization->refresh();
        });
    }
}
