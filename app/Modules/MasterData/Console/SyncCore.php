<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Console;

use App\Modules\MasterData\Support\CoreEntities;
use Illuminate\Console\Command;

final class SyncCore extends Command
{
    protected $signature = 'bara:sync-core';

    protected $description = 'Daftarkan entity master data Core (wilayah, orang, pegawai, tahun anggaran). Idempoten; jalankan saat deploy.';

    public function handle(CoreEntities $core): int
    {
        $core->sync();
        $this->info('Entity Core tersinkron.');

        return self::SUCCESS;
    }
}
