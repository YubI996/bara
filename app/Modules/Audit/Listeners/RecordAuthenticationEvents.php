<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

final readonly class RecordAuthenticationEvents
{
    public function __construct(private AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->audit->log('auth.login', $user->id, 'user', actorId: $user->id);
        }
    }

    public function handleFailed(Failed $event): void
    {
        // Email yang dicoba tidak disimpan (bisa berisi salah ketik password / data pribadi).
        $userId = $event->user instanceof User ? $event->user->id : null;
        $this->audit->log('auth.login_failed', $userId, $userId !== null ? 'user' : null);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', $event->user->id, 'user', actorId: $event->user->id);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->logTwoFactorChange($event->user, 'confirmed');
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->logTwoFactorChange($event->user, 'disabled');
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->logTwoFactorChange($event->user, 'recovery_codes_regenerated');
    }

    private function logTwoFactorChange(mixed $user, string $change): void
    {
        if ($user instanceof User) {
            $this->audit->log('auth.2fa_changed', $user->id, 'user', context: ['change' => $change], actorId: $user->id);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            RecoveryCodesGenerated::class => 'handleRecoveryCodesGenerated',
        ];
    }
}
