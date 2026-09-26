<?php

declare(strict_types=1);

namespace App\Modules\Data\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Runtime\AccessScope;
use App\Modules\Data\Runtime\ScopedRecordQuery;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\EntitySchema;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Hapus lunak (soft delete). Ditolak bila masih dirujuk relasi `restrict`. */
final readonly class DeleteRecord
{
    public function __construct(
        private ConnectionInterface $db,
        private ScopedRecordQuery $query,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(EntitySchema $schema, AccessScope $deleteScope, string $recordId): void
    {
        $this->db->transaction(function () use ($schema, $deleteScope, $recordId): void {
            $row = $this->query->records($schema->entityId, $deleteScope)
                ->where('r.id', $recordId)->lockForUpdate()->first(['r.id', 'r.title']);

            if ($row === null) {
                throw new NotFoundHttpException;
            }

            $referenced = $this->db->table('record_links as l')
                ->join('relationships as rel', 'rel.id', '=', 'l.relationship_id')
                ->join('records as src', 'src.id', '=', 'l.source_id')
                ->where('l.target_id', $recordId)
                ->where('rel.is_active', true)
                ->where('rel.on_target_delete', 'restrict')
                ->whereNull('src.deleted_at')
                ->exists();

            if ($referenced) {
                throw RecordRuleViolation::on('record', 'Data ini masih dirujuk data lain sehingga tidak dapat dihapus.');
            }

            $this->db->table('record_links as l')
                ->whereIn('l.relationship_id', fn (Builder $q) => $q->select('id')->from('relationships')->where('on_target_delete', 'nullify'))
                ->where('l.target_id', $recordId)
                ->delete();

            $this->db->table('records')->where('id', $recordId)->update(['deleted_at' => now()]);
            $this->db->table('objects')->where('id', $recordId)->update(['deleted_at' => now()]);

            $this->audit->log('record.delete', $recordId, $schema->applicationCode.'.'.$schema->entityCode, [
                'title' => [is_string($row->title ?? null) ? $row->title : null, null],
            ]);
            $this->events->record('record.deleted', $schema->applicationCode.'.'.$schema->entityCode, $recordId, [
                'entity_id' => $schema->entityId,
            ]);
        });
    }
}
