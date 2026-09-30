<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Walidata memutuskan pengajuan (approve/reject) atau mencabut persetujuan (revoke).
 * Pencabutan tidak menghapus tautan lama; tautan baru dan publikasi relasi baru ditolak.
 */
final readonly class DecideEntityConsumer
{
    /** keputusan => [status asal yang sah, status baru] */
    private const array TRANSITIONS = [
        'approve' => ['pending', 'approved'],
        'reject' => ['pending', 'rejected'],
        'revoke' => ['approved', 'revoked'],
    ];

    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    /** @param  'approve'|'reject'|'revoke'  $decision */
    public function execute(string $entityId, string $applicationId, string $decision, ?string $note, User $by): void
    {
        [$from, $to] = self::TRANSITIONS[$decision];

        if ($decision !== 'approve' && ($note === null || mb_strlen(trim($note)) < 10)) {
            throw MetadataRuleViolation::on('note', 'Tuliskan alasan (minimal 10 karakter) agar pemohon tahu tindak lanjutnya.');
        }

        $this->db->transaction(function () use ($entityId, $applicationId, $decision, $note, $by, $from, $to): void {
            $status = $this->db->table('entity_consumers')
                ->where('entity_id', $entityId)->where('application_id', $applicationId)
                ->lockForUpdate()->value('status');

            if ($status !== $from) {
                throw MetadataRuleViolation::on('consumer', 'Status pengajuan sudah berubah. Muat ulang halaman.');
            }

            $this->db->table('entity_consumers')
                ->where('entity_id', $entityId)->where('application_id', $applicationId)
                ->update([
                    'status' => $to,
                    'decided_by' => $by->id,
                    'decided_at' => now(),
                    'decision_note' => $note === null ? null : trim($note),
                ]);

            $this->audit->log("metadata.consumer_{$to}", $applicationId, 'metadata.application', ['status' => [$from, $to]], ['entity_id' => $entityId]);
            $this->events->record("metadata.consumer_{$to}", 'metadata.application', $applicationId, ['entity_id' => $entityId, 'decision' => $decision]);
        });
    }
}
