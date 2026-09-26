<?php

declare(strict_types=1);

namespace App\Modules\Access\Console;

use App\Modules\Access\Contracts\PermissionRegistry;
use Illuminate\Console\Command;

/** Jalankan setiap deploy: menyamakan permission platform & role bawaan dengan kode. */
final class SyncAccess extends Command
{
    protected $signature = 'bara:sync-access';

    protected $description = 'Sinkronkan permission platform dan role bawaan';

    public function handle(PermissionRegistry $registry): int
    {
        $registry->syncSystemRoles();
        $this->info('Permission dan role bawaan tersinkron.');

        return self::SUCCESS;
    }
}
