<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\MasterData\Support\CoreEntities;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use Illuminate\Database\ConnectionInterface;

/**
 * Penugasan pegawai (Core.Employee) pada satu unit. Objek dimiliki unit tempat bertugas, sehingga
 * cakupan unit berlaku bila kelak ada permission per unit.
 */
final readonly class AddEmployment
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private OrganizationDirectory $organizations,
        private CoreEntities $core,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(string $personId, string $orgId, ?string $nip, ?string $position, ?string $rank, string $validFrom): string
    {
        return $this->db->transaction(function () use ($personId, $orgId, $nip, $position, $rank, $validFrom): string {
            $org = $this->organizations->find($orgId);
            if ($org === null || ! $org->isActive) {
                throw MasterDataRuleViolation::on('org_id', 'Pilih unit organisasi yang masih aktif.');
            }
            if (! $this->db->table('core_persons')->where('id', $personId)->exists()) {
                throw MasterDataRuleViolation::on('person', 'Data orang tidak ditemukan.');
            }
            if ($nip !== null && $this->db->table('core_employees')->where('nip', $nip)->exists()) {
                throw MasterDataRuleViolation::on('nip', 'NIP sudah terdaftar pada penugasan lain.');
            }

            $id = $this->objects->register($this->core->id('employee'), $org->id, $org->path, Visibility::Internal);
            $this->db->table('core_employees')->insert([
                'id' => $id, 'person_id' => $personId, 'nip' => $nip, 'org_id' => $org->id,
                'position' => $position, 'rank' => $rank, 'valid_from' => $validFrom,
            ]);

            $this->audit->log('masterdata.employment_added', $id, 'core.employee', ['org_id' => [null, $org->id], 'position' => [null, $position]], ['person_id' => $personId]);
            $this->events->record('masterdata.employment_added', 'core.employee', $id, ['person_id' => $personId, 'org_id' => $org->id]);

            return $id;
        });
    }
}
