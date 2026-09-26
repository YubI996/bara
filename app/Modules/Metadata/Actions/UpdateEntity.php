<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Data\EntityData;
use App\Modules\Metadata\Models\Entity;
use Illuminate\Database\ConnectionInterface;

/**
 * Atribut entity yang tidak berversi (nama, deskripsi, visibilitas, template judul, shared).
 * Kode entity tetap.
 */
final readonly class UpdateEntity
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity, EntityData $data): Entity
    {
        return $this->db->transaction(function () use ($entity, $data): Entity {
            $entity = Entity::query()->lockForUpdate()->findOrFail($entity->id);

            if ($entity->is_system) {
                throw MetadataRuleViolation::on('name', 'Entity sistem tidak dapat diubah dari sini.');
            }

            if ($entity->is_shared && ! $data->isShared
                && $this->db->table('relationships')->where('target_entity_id', $entity->id)->where('is_active', true)
                    ->whereNot('source_entity_id', $entity->id)->exists()) {
                throw MetadataRuleViolation::on('is_shared', 'Entity masih dipakai sebagai target relasi oleh entity lain, sehingga tidak bisa berhenti dibagikan.');
            }

            $entity->fill([
                'name' => $data->name,
                'name_plural' => $data->namePlural,
                'description' => $data->description,
                'default_visibility' => $data->defaultVisibility,
                'is_shared' => $data->isShared,
                'title_template' => $data->titleTemplate,
            ]);

            $changes = [];
            foreach ($entity->getDirty() as $key => $value) {
                $changes[$key] = [$entity->getOriginal($key), $value];
            }

            if ($changes !== []) {
                $entity->save();
                $this->audit->log('entity.update', $entity->id, 'metadata.entity', $changes);
            }

            return $entity;
        });
    }
}
