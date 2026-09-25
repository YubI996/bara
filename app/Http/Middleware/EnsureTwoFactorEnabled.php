<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Area admin wajib 2FA (docs/05 §4, ADR 0013). Pengguna tanpa 2FA diarahkan ke pengaturan keamanan.
 */
final class EnsureTwoFactorEnabled
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasEnabledTwoFactorAuthentication()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Aktifkan autentikasi dua faktor (2FA) terlebih dahulu untuk membuka menu administrasi.',
            ]);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
