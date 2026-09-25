<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/** Permission level platform. Permission aplikasi dibuat saat entity dipublikasikan (M1). */
enum PlatformPermission: string
{
    case OrganizationView = 'platform.organization.view';
    case OrganizationManage = 'platform.organization.manage';
    case AuditView = 'platform.audit.view';

    public function description(): string
    {
        return match ($this) {
            self::OrganizationView => 'Melihat struktur organisasi',
            self::OrganizationManage => 'Mengelola struktur organisasi (tambah, ubah, pindah, nonaktifkan)',
            self::AuditView => 'Melihat audit log',
        };
    }
}
