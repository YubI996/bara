<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

use App\Shared\Data\DataClassification;

/** Role bawaan platform (docs/05 §2). Isi permission-nya disinkronkan oleh `bara:sync-access`. */
enum SystemRole: string
{
    case PlatformAdmin = 'platform_admin';
    case Auditor = 'auditor';
    case Dpo = 'dpo';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Administrator Platform',
            self::Auditor => 'Auditor',
            self::Dpo => 'Pejabat Pelindungan Data Pribadi',
        };
    }

    public function clearance(): DataClassification
    {
        return match ($this) {
            self::PlatformAdmin, self::Auditor => DataClassification::Restricted,
            self::Dpo => DataClassification::PersonalSpecific,
        };
    }

    /**
     * Administrator sengaja TIDAK memegang PrivacyReview: persetujuan data pribadi
     * dipisahkan dari pengelolaan metadata (separation of duty).
     *
     * @return list<PlatformPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::PlatformAdmin => [
                PlatformPermission::OrganizationView,
                PlatformPermission::OrganizationManage,
                PlatformPermission::AuditView,
                PlatformPermission::MetadataManage,
                PlatformPermission::MetadataPublish,
            ],
            self::Auditor => [PlatformPermission::OrganizationView, PlatformPermission::AuditView],
            self::Dpo => [
                PlatformPermission::OrganizationView,
                PlatformPermission::AuditView,
                PlatformPermission::PrivacyReview,
            ],
        };
    }
}
