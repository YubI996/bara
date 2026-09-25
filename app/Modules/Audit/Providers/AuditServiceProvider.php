<?php

declare(strict_types=1);

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Console\EnsureAuditPartitions;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Audit\Infrastructure\DatabaseAuditLogger;
use App\Modules\Audit\Listeners\RecordAuthenticationEvents;
use App\Shared\Support\TraceContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuditLogger::class, fn (Application $app): AuditLogger => new DatabaseAuditLogger(
            $app->make('db')->connection(),
            $app->make('auth'),
            $app->make(TraceContext::class),
            $app->bound('request') ? $app->make(Request::class) : null,
        ));
    }

    public function boot(): void
    {
        Event::subscribe(RecordAuthenticationEvents::class);

        if ($this->app->runningInConsole()) {
            $this->commands([EnsureAuditPartitions::class]);
        }
    }
}
