<?php

declare(strict_types=1);

namespace App\Modules\Data\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Runtime\AccessScope;
use App\Modules\Data\Runtime\FileStore;
use App\Modules\Data\Runtime\JsonData;
use App\Modules\Data\Runtime\RecordInput;
use App\Modules\Data\Runtime\RecordWriter;
use App\Modules\Data\Runtime\ScopedRecordQuery;
use App\Modules\Data\Runtime\TitleRenderer;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\EntitySchema;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ubah record dengan optimistic locking (`lock_version`). Field yang tidak boleh ditulis user
 * (di atas clearance) dipertahankan dari nilai lama.
 */
final readonly class UpdateRecord
{
    public const string CONFLICT_MESSAGE = 'Data ini sudah diubah pengguna lain sejak Anda membukanya. Muat ulang halaman untuk melihat versi terbaru, lalu ulangi perubahan Anda.';

    public function __construct(
        private ConnectionInterface $db,
        private ScopedRecordQuery $query,
        private RecordWriter $writer,
        private FileStore $files,
        private TitleRenderer $titles,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(EntitySchema $schema, User $user, AccessScope $updateScope, string $recordId, int $expectedLock, RecordInput $input): void
    {
        $this->db->transaction(function () use ($schema, $user, $updateScope, $recordId, $expectedLock, $input): void {
            $row = $this->query->records($schema->entityId, $updateScope)
                ->where('r.id', $recordId)->lockForUpdate()->first(['r.data', 'r.lock_version']);

            if ($row === null) {
                throw new NotFoundHttpException;
            }

            if (! is_numeric($row->lock_version ?? null) || (int) $row->lock_version !== $expectedLock) {
                throw ValidationException::withMessages(['lock_version' => self::CONFLICT_MESSAGE]);
            }

            $before = JsonData::decode($row->data ?? null);
            $after = [...$before, ...$input->values];

            foreach ($input->keptFiles as $fieldKey => $kept) {
                $ids = $this->files->ownedBy($recordId, $fieldKey, $kept);
                foreach ($input->uploads[$fieldKey] ?? [] as $upload) {
                    $ids[] = $this->files->store($upload, $recordId, $fieldKey, $schema->fieldByKey($fieldKey)?->classification->value ?? 'internal', $user->id);
                }
                $after[$fieldKey] = $ids;
            }

            $this->writer->assertUnique($schema, $after, $recordId);

            $beforeLinks = $this->writer->links($schema, $recordId);
            $changes = $this->writer->diff($schema, [...$before, ...$beforeLinks], [...$after, ...array_replace($beforeLinks, $input->links)]);

            $this->db->table('records')->where('id', $recordId)->update([
                'entity_version_id' => $schema->entityVersionId,
                'data' => json_encode((object) $after, JSON_THROW_ON_ERROR),
                'title' => $this->titles->render($schema, $after),
                'lock_version' => $expectedLock + 1,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ]);

            $this->writer->syncLinks($schema, $recordId, $input->links);

            if ($changes === []) {
                return;
            }

            $this->audit->log('record.update', $recordId, $schema->applicationCode.'.'.$schema->entityCode, $changes, [
                'version' => $schema->version,
            ]);
            $this->events->record('record.updated', $schema->applicationCode.'.'.$schema->entityCode, $recordId, [
                'entity_id' => $schema->entityId,
                'changed_fields' => array_keys($changes),
            ]);
        });
    }
}
