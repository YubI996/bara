<?php

declare(strict_types=1);

namespace App\Modules\Data\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use App\Modules\Data\Runtime\AccessScope;
use App\Modules\Data\Runtime\FileStore;
use App\Modules\Data\Runtime\RecordInput;
use App\Modules\Data\Runtime\RecordWriter;
use App\Modules\Data\Runtime\TitleRenderer;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/** docs/06 §3 "Action: CreateRecord". */
final readonly class CreateRecord
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private OrganizationDirectory $organizations,
        private RecordWriter $writer,
        private FileStore $files,
        private TitleRenderer $titles,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(EntitySchema $schema, User $user, AccessScope $createScope, string $ownerOrgId, RecordInput $input): string
    {
        $owner = $this->organizations->find($ownerOrgId);

        if ($owner === null || ! $owner->isActive || ! $createScope->covers($owner->path, 'private')) {
            throw RecordRuleViolation::on('owner_org_id', 'Pilih unit pemilik yang aktif dan dalam kewenangan Anda.');
        }

        return $this->db->transaction(function () use ($schema, $user, $owner, $input): string {
            $this->writer->assertUnique($schema, $input->values);

            $id = Str::uuid7()->toString();
            $this->objects->register(
                entityId: $schema->entityId,
                ownerOrgId: $owner->id,
                ownerPath: $owner->path,
                visibility: Visibility::from($schema->defaultVisibility),
                id: $id,
            );

            $data = $input->values;
            foreach ($input->uploads as $fieldKey => $uploads) {
                $field = $schema->fieldByKey($fieldKey);
                $ids = [];
                foreach ($uploads as $upload) {
                    $ids[] = $this->files->store($upload, $id, $fieldKey, $field?->classification->value ?? 'internal', $user->id);
                }
                $data[$fieldKey] = $ids;
            }

            $this->db->table('records')->insert([
                'id' => $id,
                'entity_id' => $schema->entityId,
                'entity_version_id' => $schema->entityVersionId,
                'data' => json_encode((object) $data, JSON_THROW_ON_ERROR),
                'title' => $this->titles->render($schema, $data),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->writer->syncLinks($schema, $id, $input->links);

            $this->audit->log('record.create', $id, $schema->applicationCode.'.'.$schema->entityCode, $this->writer->diff($schema, [], $data), [
                'owner_org_id' => $owner->id,
                'version' => $schema->version,
            ]);
            $this->events->record('record.created', $schema->applicationCode.'.'.$schema->entityCode, $id, [
                'entity_id' => $schema->entityId,
                'owner_org_id' => $owner->id,
            ]);

            return $id;
        });
    }
}
