<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Area data (/apps): 2FA wajib bagi pemegang role berisiko tinggi (ADR 0013). Operator dan
 * viewer ber-clearance internal tetap bisa masuk hanya dengan password.
 */
final readonly class EnsureTwoFactorForPrivilegedRoles
{
    public function __construct(private AccessChecker $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasEnabledTwoFactorAuthentication() && $this->access->requiresTwoFactor($user)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Peran Anda memberi akses luas ke data. Aktifkan autentikasi dua faktor (2FA) terlebih dahulu.',
            ]);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
