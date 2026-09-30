<?php

declare(strict_types=1);

namespace App\Modules\Access\Console;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

/**
 * Penugasan role sementara lewat CLI sampai UI penugasan tersedia (M5).
 * Contoh: php artisan bara:assign-role operator@pemda.go.id operator dinkes --app=monev
 */
final class AssignRole extends Command
{
    protected $signature = 'bara:assign-role
        {email : Email user}
        {role : Kode role (platform_admin, dpo, auditor, atau role aplikasi: app_admin, operator, viewer)}
        {org : Kode unit organisasi sebagai scope}
        {--app= : Kode aplikasi untuk role aplikasi}
        {--exact : Hanya unit itu, tanpa unit di bawahnya}
        {--force : Wajib untuk role platform berisiko tinggi (platform_admin, dpo)}';

    protected $description = 'Beri role kepada user pada scope unit organisasi (tercatat di audit + outbox)';

    /** Role platform yang membuka akses luas; pemberiannya harus disengaja (--force). */
    private const array HIGH_RISK_ROLES = ['platform_admin', 'dpo', 'data_steward'];

    public function handle(ConnectionInterface $db, AuditLogger $audit, EventRecorder $events): int
    {
        $roleCode = (string) $this->argument('role');
        $user = $db->table('users')->where('email', $this->argument('email'))->first(['id', 'is_active']);
        $org = $db->table('core_organizations')->where('code', $this->argument('org'))
            ->first(['id', 'valid_to']);
        $appCode = $this->option('app');
        $appId = is_string($appCode) ? $db->table('applications')->where('code', $appCode)->value('id') : null;

        if (is_string($appCode) && ! is_string($appId)) {
            $this->error("Aplikasi {$appCode} tidak ditemukan.");

            return self::FAILURE;
        }

        $roleId = $db->table('roles')->where('code', $roleCode)
            ->when(is_string($appId), fn ($q) => $q->where('application_id', $appId), fn ($q) => $q->whereNull('application_id'))
            ->value('id');

        if (! is_object($user) || ! is_string($user->id ?? null) || ! is_object($org) || ! is_string($org->id ?? null) || ! is_string($roleId)) {
            $this->error('User, unit, atau role tidak ditemukan. Role aplikasi baru ada setelah entity pertama dipublikasikan.');

            return self::FAILURE;
        }

        if (($user->is_active ?? false) !== true) {
            $this->error('User nonaktif tidak bisa diberi role.');

            return self::FAILURE;
        }

        if (is_string($org->valid_to ?? null) && $org->valid_to <= now()->toDateString()) {
            $this->error('Unit sudah nonaktif; pilih unit yang masih berlaku.');

            return self::FAILURE;
        }

        if (! is_string($appId) && in_array($roleCode, self::HIGH_RISK_ROLES, true) && ! $this->option('force')) {
            $this->error("Role {$roleCode} membuka akses luas. Ulangi dengan --force bila memang disengaja.");

            return self::FAILURE;
        }

        $includeDescendants = ! $this->option('exact');
        $userId = $user->id;
        $orgId = $org->id;

        $db->transaction(function () use ($db, $audit, $events, $userId, $roleId, $orgId, $roleCode, $appCode, $includeDescendants): void {
            $db->table('role_assignments')->upsert([[
                'user_id' => $userId,
                'role_id' => $roleId,
                'scope_org_id' => $orgId,
                'include_descendants' => $includeDescendants,
            ]], ['user_id', 'role_id', 'scope_org_id'], ['include_descendants']);

            // Identitas operator CLI: tidak ada user web, jadi catat akun OS dan host.
            $audit->log('role.assigned', $userId, 'user', context: [
                'role' => $roleCode,
                'application' => is_string($appCode) ? $appCode : null,
                'scope_org_id' => $orgId,
                'include_descendants' => $includeDescendants,
                'via' => 'cli',
                'os_user' => get_current_user(),
                'host' => gethostname() ?: null,
            ]);
            // docs/05 §1.1: dipakai untuk invalidasi cache scope.
            $events->record('role.assigned', 'user', $userId, [
                'role_id' => $roleId,
                'scope_org_id' => $orgId,
                'include_descendants' => $includeDescendants,
            ]);
        });

        $this->info($includeDescendants ? 'Role diberikan untuk unit ini beserta seluruh unit di bawahnya.' : 'Role diberikan hanya untuk unit ini.');

        return self::SUCCESS;
    }
}
