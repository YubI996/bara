<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Providers;

use App\Modules\Metadata\Contracts\EntityCatalog;
use App\Modules\Metadata\Infrastructure\DatabaseEntityCatalog;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EntityCatalog::class, fn (Application $app): EntityCatalog => new DatabaseEntityCatalog(
            $app->make('db')->connection(),
        ));
    }
}
