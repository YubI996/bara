<?php

declare(strict_types=1);

namespace App\Modules\Access\Providers;

use App\Modules\Access\Console\SyncAccess;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PermissionRegistry;
use App\Modules\Access\Infrastructure\DatabaseAccessChecker;
use App\Modules\Access\Infrastructure\DatabasePermissionRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessChecker::class, fn (Application $app): AccessChecker => new DatabaseAccessChecker(
            $app->make('db')->connection(),
        ));
        $this->app->scoped(PermissionRegistry::class, fn (Application $app): PermissionRegistry => new DatabasePermissionRegistry(
            $app->make('db')->connection(),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncAccess::class]);
        }
    }
}
