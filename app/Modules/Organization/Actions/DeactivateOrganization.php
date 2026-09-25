<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Menonaktifkan unit (nomenklatur berubah/dibubarkan). Data historis tetap, tidak dihapus.
 */
final readonly class DeactivateOrganization
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(Organization $organization): Organization
    {
        return $this->db->transaction(function () use ($organization): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            if ($organization->isRoot()) {
                throw OrganizationRuleViolation::on('organization', 'Unit akar Pemda tidak dapat dinonaktifkan.');
            }

            if (! $organization->isActive()) {
                throw OrganizationRuleViolation::on('organization', 'Unit sudah nonaktif.');
            }

            if ($organization->children()->active()->exists()) {
                throw OrganizationRuleViolation::on('organization', 'Nonaktifkan atau pindahkan dulu semua unit di bawahnya.');
            }

            $today = CarbonImmutable::today();
            $organization->valid_to = $today->lessThan($organization->valid_from) ? $organization->valid_from : $today;
            $organization->save();

            $this->audit->log('organization.deactivate', $organization->id, 'core.organization', [
                'valid_to' => [null, $organization->valid_to->toDateString()],
            ]);
            $this->events->record('organization.deactivated', 'core.organization', $organization->id);

            return $organization;
        });
    }
}
