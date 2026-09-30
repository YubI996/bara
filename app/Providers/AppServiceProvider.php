<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Support\TraceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TraceContext::class);
    }

    public function boot(): void
    {
        $this->guardProductionConfiguration();
        $this->configureTrustedProxies();
        $this->configureDefaults();

        // Migration dipisah per modul (docs/03 §4); urutan tetap mengikuti nama file.
        $this->loadMigrationsFrom(array_map(
            fn (string $module): string => database_path("migrations/{$module}"),
            ['platform', 'identity', 'access', 'audit', 'eventing', 'metadata', 'masterdata'],
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

    /**
     * SEC-009/SEC-014: konfigurasi yang membocorkan data atau mematikan kontrol keamanan tidak
     * boleh berjalan di produksi. Perintah konsol tetap jalan agar operator bisa memperbaikinya.
     */
    private function guardProductionConfiguration(): void
    {
        if (! app()->isProduction() || app()->runningInConsole()) {
            return;
        }

        $problems = array_keys(array_filter([
            'APP_DEBUG harus false' => config()->boolean('app.debug'),
            'BARA_FILE_SCANNER tidak boleh none' => config()->string('bara.files.scanner') === 'none',
            'SESSION_SECURE_COOKIE harus true' => config('session.secure') !== true,
            'BARA_NIK_PEPPER & BARA_PII_KEY wajib diisi' => strlen(config()->string('bara.pii.nik_pepper')) < 32 || config()->string('bara.pii.key') === '',
        ]));

        if ($problems !== []) {
            throw new RuntimeException('Konfigurasi produksi tidak aman: '.implode('; ', $problems).'. Lihat .env.production.example.');
        }
    }

    /**
     * SEC-013: tanpa daftar proxy tepercaya, isSecure() salah di balik reverse proxy TLS →
     * HSTS tidak terkirim dan URL dibangkitkan dengan http.
     */
    private function configureTrustedProxies(): void
    {
        $proxies = trim(config()->string('bara.security.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }
}
