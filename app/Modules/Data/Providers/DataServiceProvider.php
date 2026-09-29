<?php

declare(strict_types=1);

namespace App\Modules\Data\Providers;

use App\Models\User;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Infrastructure\DatabaseObjectRegistry;
use App\Modules\Data\Listeners\ScheduleIndexSync;
use App\Modules\Data\Listeners\ScheduleRecordMigration;
use App\Modules\Data\Scanning\ClamdScanner;
use App\Modules\Data\Scanning\FileScanner;
use App\Modules\Metadata\Contracts\EntityVersionPublished;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class DataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileScanner::class, fn (): FileScanner => new ClamdScanner(
            config()->string('bara.files.clamav.socket'),
            config()->float('bara.files.clamav.timeout'),
        ));

        $this->app->scoped(ObjectRegistry::class, fn (Application $app): ObjectRegistry => new DatabaseObjectRegistry(
            $app->make('db')->connection(),
        ));
    }

    public function boot(): void
    {
        // SEC-007: batasi tulis data per user (juga menahan uji coba nilai unik berulang).
        // Pencarian relasi: selector memanggil per ketikan (debounce 300 ms).
        RateLimiter::for('runtime-lookup', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(120)->by($user instanceof User ? $user->id : (string) $request->ip());
        });
        RateLimiter::for('runtime-write', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(60)->by($user instanceof User ? $user->id : (string) $request->ip());
        });

        Event::listen(EntityVersionPublished::class, ScheduleIndexSync::class);
        Event::listen(EntityVersionPublished::class, ScheduleRecordMigration::class);
    }
}
