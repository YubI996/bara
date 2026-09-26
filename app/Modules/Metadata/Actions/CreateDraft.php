<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Models\Field;
use Illuminate\Database\ConnectionInterface;

/** Membuat draft baru dari versi terbit (field disalin dengan field_key yang sama). */
final readonly class CreateDraft
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity): EntityVersion
    {
        return $this->db->transaction(function () use ($entity): EntityVersion {
            $entity = Entity::query()->lockForUpdate()->findOrFail($entity->id);

            if ($entity->is_system) {
                throw MetadataRuleViolation::on('draft', 'Entity sistem tidak dapat diubah dari sini.');
            }
            if ($entity->draft_version_id !== null) {
                throw MetadataRuleViolation::on('draft', 'Entity ini sudah punya draft.');
            }
            if ($entity->published_version_id === null) {
                throw MetadataRuleViolation::on('draft', 'Belum ada versi terbit untuk disalin.');
            }

            $published = EntityVersion::query()->findOrFail($entity->published_version_id);
            $max = EntityVersion::query()->where('entity_id', $entity->id)->max('version');
            $next = (is_numeric($max) ? (int) $max : 0) + 1;
            $draft = EntityVersion::query()->create(['entity_id' => $entity->id, 'version' => $next, 'status' => 'draft']);

            foreach ($published->definitions() as $definition) {
                Field::query()->create([
                    'entity_version_id' => $draft->id,
                    'field_key' => $definition->fieldKey,
                    'code' => $definition->code,
                    'label' => $definition->label,
                    'help_text' => $definition->helpText,
                    'type' => $definition->type,
                    'is_required' => $definition->required,
                    'is_unique' => $definition->unique,
                    'is_indexed' => $definition->indexed,
                    'is_searchable' => $definition->searchable,
                    'classification' => $definition->classification,
                    'config' => $definition->config,
                    'position' => $definition->position,
                ]);
            }

            $entity->forceFill(['draft_version_id' => $draft->id])->save();
            $this->audit->log('metadata.draft_create', $entity->id, 'metadata.entity', context: [
                'version' => $next,
                'based_on' => $published->version,
            ]);

            return $draft;
        });
    }
}
