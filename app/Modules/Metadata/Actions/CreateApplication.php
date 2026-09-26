<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Data\ApplicationData;
use App\Modules\Metadata\Models\Application;
use Illuminate\Database\ConnectionInterface;

final readonly class CreateApplication
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(ApplicationData $data): Application
    {
        return $this->db->transaction(function () use ($data): Application {
            $application = Application::query()->create([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'owner_org_id' => $data->ownerOrgId,
                'status' => $data->status,
                'is_system' => false,
            ]);

            $this->audit->log('application.create', $application->id, 'metadata.application', [
                'code' => [null, $application->code],
                'name' => [null, $application->name],
                'owner_org_id' => [null, $application->owner_org_id],
                'status' => [null, $application->status],
            ]);
            $this->events->record('application.created', 'metadata.application', $application->id, [
                'code' => $application->code,
            ]);

            return $application;
        });
    }
}
