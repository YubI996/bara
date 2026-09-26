<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Data\EntityData;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use Illuminate\Database\ConnectionInterface;

/** Entity aplikasi selalu bertipe penyimpanan `document` (JSONB, ADR 0004). Dibuat bersama draft v1. */
final readonly class CreateEntity
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(Application $application, EntityData $data): Entity
    {
        return $this->db->transaction(function () use ($application, $data): Entity {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($application->is_system || $application->status === 'archived') {
                throw MetadataRuleViolation::on('code', 'Entity tidak bisa ditambahkan ke aplikasi sistem atau yang diarsipkan.');
            }

            $entity = Entity::query()->create([
                'application_id' => $application->id,
                'code' => $data->code,
                'name' => $data->name,
                'name_plural' => $data->namePlural,
                'description' => $data->description,
                'storage_type' => 'document',
                'is_system' => false,
                'is_shared' => $data->isShared,
                'default_visibility' => $data->defaultVisibility,
                'title_template' => $data->titleTemplate,
            ]);

            $draft = EntityVersion::query()->create(['entity_id' => $entity->id, 'version' => 1, 'status' => 'draft']);
            $entity->forceFill(['draft_version_id' => $draft->id])->save();

            $this->audit->log('entity.create', $entity->id, 'metadata.entity', [
                'code' => [null, $entity->code],
                'name' => [null, $entity->name],
                'is_shared' => [null, $entity->is_shared],
            ], ['application_id' => $application->id]);
            $this->events->record('entity.created', 'metadata.entity', $entity->id, [
                'application_id' => $application->id,
                'code' => $entity->code,
            ]);

            return $entity;
        });
    }
}
