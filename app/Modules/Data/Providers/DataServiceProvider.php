<?php

declare(strict_types=1);

namespace App\Modules\Data\Providers;

use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Infrastructure\DatabaseObjectRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class DataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ObjectRegistry::class, fn (Application $app): ObjectRegistry => new DatabaseObjectRegistry(
            $app->make('db')->connection(),
        ));
    }
}
