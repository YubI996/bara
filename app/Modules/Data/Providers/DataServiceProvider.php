<?php

declare(strict_types=1);

namespace App\Modules\Data\Providers;

use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Infrastructure\DatabaseObjectRegistry;
use App\Modules\Data\Listeners\ScheduleIndexSync;
use App\Modules\Metadata\Contracts\EntityVersionPublished;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class DataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ObjectRegistry::class, fn (Application $app): ObjectRegistry => new DatabaseObjectRegistry(
            $app->make('db')->connection(),
        ));
    }

    public function boot(): void
    {
        Event::listen(EntityVersionPublished::class, ScheduleIndexSync::class);
    }
}
