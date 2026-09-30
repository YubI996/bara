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

final readonly class CreateFiscalYear
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private OrganizationDirectory $organizations,
        private CoreEntities $core,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(int $year, string $startsOn, string $endsOn): string
    {
        return $this->db->transaction(function () use ($year, $startsOn, $endsOn): string {
            if ($this->db->table('core_fiscal_years')->where('year', $year)->exists()) {
                throw MasterDataRuleViolation::on('year', "Tahun anggaran {$year} sudah ada.");
            }

            $root = $this->organizations->root();
            $id = $this->objects->register($this->core->id('fiscal_year'), $root->id, $root->path, Visibility::Public);
            $this->db->table('core_fiscal_years')->insert([
                'id' => $id, 'year' => $year, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'status' => 'planning',
            ]);

            $this->audit->log('masterdata.fiscal_year_created', $id, 'core.fiscal_year', ['year' => [null, $year]]);
            $this->events->record('masterdata.fiscal_year_created', 'core.fiscal_year', $id, ['year' => $year]);

            return $id;
        });
    }
}
