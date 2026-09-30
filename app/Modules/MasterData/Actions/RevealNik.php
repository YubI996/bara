<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\MasterData\Support\PiiCipher;
use Illuminate\Database\ConnectionInterface;

/**
 * Membuka NIK lengkap (docs/04 §5): wajib alasan, tercatat `pii.revealed` di audit (tanpa NIK)
 * dan outbox, dalam satu transaksi dengan pembacaannya.
 */
final readonly class RevealNik
{
    public function __construct(
        private ConnectionInterface $db,
        private PiiCipher $cipher,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(string $personId, User $by, string $reason): string
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw MasterDataRuleViolation::on('reason', 'Tuliskan alasan membuka NIK (minimal 10 karakter).');
        }

        return $this->db->transaction(function () use ($personId, $by, $reason): string {
            $payload = $this->db->table('core_persons')->where('id', $personId)->value('nik_enc');

            if (! is_string($payload)) {
                throw MasterDataRuleViolation::on('reason', 'Orang ini tidak memiliki NIK tersimpan.');
            }

            $this->audit->log('pii.revealed', $personId, 'core.person', context: ['field' => 'nik', 'reason' => mb_substr(trim($reason), 0, 500)], actorId: $by->id);
            $this->events->record('pii.revealed', 'core.person', $personId, ['field' => 'nik', 'actor_id' => $by->id]);

            return $this->cipher->decrypt($payload);
        });
    }
}
