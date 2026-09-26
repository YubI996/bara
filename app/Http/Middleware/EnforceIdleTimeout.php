<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle timeout sesi (docs/05 §6): pengguna ber-role berisiko tinggi (admin, platform, clearance
 * di atas internal) keluar otomatis setelah `bara.security.privileged_idle_minutes` tanpa
 * aktivitas; pengguna lain mengikuti SESSION_LIFETIME. Batas yang berlaku dibagikan ke UI
 * (atribut request) untuk peringatan sebelum sesi habis (WCAG 2.2.1).
 */
final readonly class EnforceIdleTimeout
{
    public const string SESSION_KEY = 'bara.last_activity_at';

    public const string ATTRIBUTE = 'bara.idle_minutes';

    public function __construct(private AccessChecker $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $minutes = $this->access->requiresTwoFactor($user)
            ? config()->integer('bara.security.privileged_idle_minutes')
            : config()->integer('session.lifetime');
        $last = $session->get(self::SESSION_KEY);
        $now = time();

        if (is_int($last) && $now - $last > $minutes * 60) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('status', "Sesi Anda berakhir karena tidak ada aktivitas selama {$minutes} menit. Silakan masuk kembali.");
        }

        $session->put(self::SESSION_KEY, $now);
        $request->attributes->set(self::ATTRIBUTE, $minutes);

        return $next($request);
    }
}
