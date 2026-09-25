<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Support\TraceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TraceContext::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();

        // Migration dipisah per modul (docs/03 §4); urutan tetap mengikuti nama file.
        $this->loadMigrationsFrom(array_map(
            fn (string $module): string => database_path("migrations/{$module}"),
            ['platform', 'identity', 'access', 'audit', 'eventing'],
        ));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        // Tangkap lazy loading (N+1), atribut tak dikenal, dan akses atribut hilang saat pengembangan.
        Model::shouldBeStrict(! app()->isProduction());

        // docs/05 §6: minimal 12 karakter; cek kebocoran (HIBP) hanya di produksi karena butuh jaringan.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : Password::min(12));
    }
}
