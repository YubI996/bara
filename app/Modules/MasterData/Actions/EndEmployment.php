<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Database\ConnectionInterface;

/** Mengakhiri penugasan (mutasi/pensiun): valid_to diisi, baris tidak dihapus (histori). */
final readonly class EndEmployment
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(string $employeeId, string $validTo): void
    {
        $this->db->transaction(function () use ($employeeId, $validTo): void {
            $row = $this->db->table('core_employees')->where('id', $employeeId)->lockForUpdate()->first(['valid_from', 'valid_to']);

            if (! is_object($row) || ($row->valid_to ?? null) !== null) {
                throw MasterDataRuleViolation::on('valid_to', 'Penugasan tidak ditemukan atau sudah berakhir.');
            }
            if (is_string($row->valid_from ?? null) && $validTo < $row->valid_from) {
                throw MasterDataRuleViolation::on('valid_to', 'Tanggal berakhir tidak boleh sebelum tanggal mulai.');
            }

            $this->db->table('core_employees')->where('id', $employeeId)->update(['valid_to' => $validTo]);
            $this->audit->log('masterdata.employment_ended', $employeeId, 'core.employee', ['valid_to' => [null, $validTo]]);
            $this->events->record('masterdata.employment_ended', 'core.employee', $employeeId, ['valid_to' => $validTo]);
        });
    }
}
