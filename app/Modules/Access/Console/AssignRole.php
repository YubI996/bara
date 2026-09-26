<?php

declare(strict_types=1);

namespace App\Modules\Access\Console;

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
        {--exact : Hanya unit itu, tanpa unit di bawahnya}';

    protected $description = 'Beri role kepada user pada scope unit organisasi';

    public function handle(ConnectionInterface $db): int
    {
        $userId = $db->table('users')->where('email', $this->argument('email'))->value('id');
        $orgId = $db->table('core_organizations')->where('code', $this->argument('org'))->value('id');
        $appCode = $this->option('app');
        $appId = is_string($appCode) ? $db->table('applications')->where('code', $appCode)->value('id') : null;

        if (is_string($appCode) && ! is_string($appId)) {
            $this->error("Aplikasi {$appCode} tidak ditemukan.");

            return self::FAILURE;
        }

        $roleId = $db->table('roles')->where('code', $this->argument('role'))
            ->when(is_string($appId), fn ($q) => $q->where('application_id', $appId), fn ($q) => $q->whereNull('application_id'))
            ->value('id');

        if (! is_string($userId) || ! is_string($orgId) || ! is_string($roleId)) {
            $this->error('User, unit, atau role tidak ditemukan. Role aplikasi baru ada setelah entity pertama dipublikasikan.');

            return self::FAILURE;
        }

        $db->table('role_assignments')->upsert([[
            'user_id' => $userId,
            'role_id' => $roleId,
            'scope_org_id' => $orgId,
            'include_descendants' => ! $this->option('exact'),
        ]], ['user_id', 'role_id', 'scope_org_id'], ['include_descendants']);

        $db->table('audit_logs')->insert([
            'actor_type' => 'system',
            'action' => 'role.assigned',
            'object_id' => $userId,
            'object_type' => 'user',
            'context' => json_encode(['role_id' => $roleId, 'scope_org_id' => $orgId, 'via' => 'cli'], JSON_THROW_ON_ERROR),
        ]);

        $this->info('Role diberikan.');

        return self::SUCCESS;
    }
}
