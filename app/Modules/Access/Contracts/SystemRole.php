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
    case DataSteward = 'data_steward';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Administrator Platform',
            self::Auditor => 'Auditor',
            self::Dpo => 'Pejabat Pelindungan Data Pribadi',
            self::DataSteward => 'Walidata',
        };
    }

    public function clearance(): DataClassification
    {
        return match ($this) {
            self::PlatformAdmin, self::Auditor => DataClassification::Restricted,
            self::DataSteward => DataClassification::Internal,
            self::Dpo => DataClassification::PersonalSpecific,
        };
    }

    /**
     * Administrator sengaja TIDAK memegang PrivacyReview, ConsumerApprove, maupun PiiReveal:
     * persetujuan dipisahkan dari pengelolaan (separation of duty).
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
                PlatformPermission::MasterDataView,
            ],
            self::Auditor => [PlatformPermission::OrganizationView, PlatformPermission::AuditView, PlatformPermission::MasterDataView],
            self::Dpo => [
                PlatformPermission::OrganizationView,
                PlatformPermission::AuditView,
                PlatformPermission::PrivacyReview,
            ],
            // Walidata (Perpres 39/2019): pemilik master data dan gerbang pemakaian entity bersama.
            // Terpisah dari admin platform: admin mengajukan, Walidata menyetujui.
            self::DataSteward => [
                PlatformPermission::OrganizationView,
                PlatformPermission::MasterDataView,
                PlatformPermission::MasterDataManage,
                PlatformPermission::ConsumerApprove,
                PlatformPermission::PiiReveal,
            ],
        };
    }
}
