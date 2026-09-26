<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/** Permission level platform. Permission aplikasi dibuat saat entity dipublikasikan. */
enum PlatformPermission: string
{
    case OrganizationView = 'platform.organization.view';
    case OrganizationManage = 'platform.organization.manage';
    case AuditView = 'platform.audit.view';
    case MetadataManage = 'platform.metadata.manage';
    case MetadataPublish = 'platform.metadata.publish';
    case PrivacyReview = 'platform.privacy.review';

    public function description(): string
    {
        return match ($this) {
            self::OrganizationView => 'Melihat struktur organisasi',
            self::OrganizationManage => 'Mengelola struktur organisasi (tambah, ubah, pindah, nonaktifkan)',
            self::AuditView => 'Melihat audit log',
            self::MetadataManage => 'Mengelola aplikasi, entity, dan draft field',
            self::MetadataPublish => 'Mempublikasikan versi entity',
            self::PrivacyReview => 'Menyetujui field berisi data pribadi (Pejabat PDP)',
        };
    }
}
