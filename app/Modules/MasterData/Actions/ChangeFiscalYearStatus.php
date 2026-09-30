<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Siklus tahun anggaran: planning → running → closed. Hanya satu yang berjalan; menjalankan
 * tahun baru menutup tahun berjalan sebelumnya dalam transaksi yang sama.
 */
final readonly class ChangeFiscalYearStatus
{
    private const array NEXT = ['planning' => 'running', 'running' => 'closed'];

    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(string $id, string $to): void
    {
        $this->db->transaction(function () use ($id, $to): void {
            $row = $this->db->table('core_fiscal_years')->where('id', $id)->lockForUpdate()->first(['status', 'year']);
            $from = is_object($row) && is_string($row->status ?? null) ? $row->status : null;

            if ($from === null || (self::NEXT[$from] ?? null) !== $to) {
                throw MasterDataRuleViolation::on('status', 'Perubahan status tidak sah. Urutannya: Perencanaan → Berjalan → Ditutup.');
            }

            if ($to === 'running') {
                foreach ($this->db->table('core_fiscal_years')->where('status', 'running')->lockForUpdate()->pluck('id') as $runningId) {
                    if (is_string($runningId)) {
                        $this->db->table('core_fiscal_years')->where('id', $runningId)->update(['status' => 'closed']);
                        $this->audit->log('masterdata.fiscal_year_status', $runningId, 'core.fiscal_year', ['status' => ['running', 'closed']]);
                    }
                }
            }

            $this->db->table('core_fiscal_years')->where('id', $id)->update(['status' => $to]);
            $this->audit->log('masterdata.fiscal_year_status', $id, 'core.fiscal_year', ['status' => [$from, $to]]);
            $this->events->record('masterdata.fiscal_year_status_changed', 'core.fiscal_year', $id, ['from' => $from, 'to' => $to]);
        });
    }
}
