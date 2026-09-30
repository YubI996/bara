<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Providers;

use App\Modules\MasterData\Console\SyncCore;
use Illuminate\Support\ServiceProvider;

final class MasterDataServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncCore::class]);
        }
    }
}
