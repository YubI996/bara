<?php

declare(strict_types=1);

namespace App\Modules\Access\Providers;

use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Infrastructure\DatabaseAccessChecker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessChecker::class, fn (Application $app): AccessChecker => new DatabaseAccessChecker(
            $app->make('db')->connection(),
        ));
    }
}
