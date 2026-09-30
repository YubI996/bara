<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Providers;

use App\Modules\MasterData\Console\GeneratePiiKey;
use App\Modules\MasterData\Console\ImportRegionsCommand;
use App\Modules\MasterData\Console\SyncCore;
use App\Modules\MasterData\Support\PiiCipher;
use Illuminate\Support\ServiceProvider;

final class MasterDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PiiCipher::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncCore::class, ImportRegionsCommand::class, GeneratePiiKey::class]);
        }
    }
}
