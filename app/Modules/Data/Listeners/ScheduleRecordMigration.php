<?php

declare(strict_types=1);

namespace App\Modules\Data\Listeners;

use App\Modules\Data\Jobs\MigrateRecordData;
use App\Modules\Metadata\Contracts\EntityVersionPublished;
use App\Modules\Metadata\Contracts\FieldMigration;

final class ScheduleRecordMigration
{
    public function handle(EntityVersionPublished $event): void
    {
        if ($event->migrations === []) {
            return;
        }

        MigrateRecordData::dispatch(
            $event->entityId,
            $event->entityVersionId,
            array_map(static fn (FieldMigration $m): array => $m->toArray(), $event->migrations),
        )->afterCommit();
    }
}
