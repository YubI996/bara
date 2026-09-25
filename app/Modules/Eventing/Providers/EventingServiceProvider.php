<?php

declare(strict_types=1);

namespace App\Modules\Eventing\Providers;

use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Eventing\Infrastructure\OutboxEventRecorder;
use App\Shared\Support\TraceContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class EventingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EventRecorder::class, fn (Application $app): EventRecorder => new OutboxEventRecorder(
            $app->make('db')->connection(),
            $app->make(TraceContext::class),
        ));
    }
}
