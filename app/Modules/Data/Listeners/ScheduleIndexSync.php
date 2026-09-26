<?php

declare(strict_types=1);

namespace App\Modules\Data\Listeners;

use App\Modules\Data\Jobs\EnsureRecordIndexes;
use App\Modules\Metadata\Contracts\EntityVersionPublished;

/** Index dibuat setelah transaksi publikasi commit (CREATE INDEX CONCURRENTLY tak boleh dalam transaksi). */
final class ScheduleIndexSync
{
    public function handle(EntityVersionPublished $event): void
    {
        EnsureRecordIndexes::dispatch($event->entityId)->afterCommit();
    }
}
