<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Data\ApplicationData;
use App\Modules\Metadata\Models\Application;
use Illuminate\Database\ConnectionInterface;

/** Kode aplikasi tetap (dipakai sebagai prefiks permission & URL). */
final readonly class UpdateApplication
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(Application $application, ApplicationData $data): Application
    {
        return $this->db->transaction(function () use ($application, $data): Application {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($application->is_system) {
                throw MetadataRuleViolation::on('name', 'Aplikasi sistem tidak dapat diubah dari sini.');
            }

            $application->fill([
                'name' => $data->name,
                'description' => $data->description,
                'owner_org_id' => $data->ownerOrgId,
                'status' => $data->status,
            ]);

            $changes = [];
            foreach ($application->getDirty() as $key => $value) {
                $changes[$key] = [$application->getOriginal($key), $value];
            }

            if ($changes === []) {
                return $application;
            }

            $application->save();

            $this->audit->log('application.update', $application->id, 'metadata.application', $changes);
            $this->events->record('application.updated', 'metadata.application', $application->id, [
                'changed_fields' => array_keys($changes),
            ]);

            return $application;
        });
    }
}
