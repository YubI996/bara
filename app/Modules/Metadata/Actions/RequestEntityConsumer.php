<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use Illuminate\Database\ConnectionInterface;

/**
 * Admin aplikasi mengajukan pemakaian entity bersama milik aplikasi lain / Core (ADR 0016).
 * Pengajuan yang pernah ditolak/dicabut boleh diajukan ulang.
 */
final readonly class RequestEntityConsumer
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(Application $application, string $entityId, string $reason, User $by): void
    {
        $this->db->transaction(function () use ($application, $entityId, $reason, $by): void {
            $entity = Entity::query()->find($entityId);

            if ($entity === null || ! $entity->is_shared || $entity->application_id === $application->id) {
                throw MetadataRuleViolation::on('entity_id', 'Pilih entity bersama (shared) milik aplikasi lain atau master data Core.');
            }
            if ($entity->published_version_id === null && $entity->storage_type !== 'physical') {
                throw MetadataRuleViolation::on('entity_id', 'Entity itu belum dipublikasikan.');
            }

            $current = $this->db->table('entity_consumers')
                ->where('entity_id', $entityId)->where('application_id', $application->id)
                ->lockForUpdate()->value('status');

            if ($current === 'pending' || $current === 'approved') {
                throw MetadataRuleViolation::on('entity_id', $current === 'pending'
                    ? 'Pengajuan untuk entity ini masih menunggu keputusan Walidata.'
                    : 'Aplikasi ini sudah disetujui memakai entity tersebut.');
            }

            $this->db->table('entity_consumers')->upsert([[
                'entity_id' => $entityId,
                'application_id' => $application->id,
                'access' => 'reference',
                'status' => 'pending',
                'reason' => $reason,
                'requested_by' => $by->id,
                'requested_at' => now(),
                'decided_by' => null,
                'decided_at' => null,
                'decision_note' => null,
            ]], ['entity_id', 'application_id'], ['status', 'reason', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_note']);

            $this->audit->log('metadata.consumer_requested', $application->id, 'metadata.application', context: ['entity_id' => $entityId]);
            $this->events->record('metadata.consumer_requested', 'metadata.application', $application->id, ['entity_id' => $entityId]);
        });
    }
}
