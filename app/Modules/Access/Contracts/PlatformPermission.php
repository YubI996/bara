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
    case MasterDataView = 'platform.masterdata.view';
    case MasterDataManage = 'platform.masterdata.manage';
    case ConsumerApprove = 'platform.consumer.approve';
    case PiiReveal = 'platform.pii.reveal';

    public function description(): string
    {
        return match ($this) {
            self::OrganizationView => 'Melihat struktur organisasi',
            self::OrganizationManage => 'Mengelola struktur organisasi (tambah, ubah, pindah, nonaktifkan)',
            self::AuditView => 'Melihat audit log',
            self::MetadataManage => 'Mengelola aplikasi, entity, dan draft field',
            self::MetadataPublish => 'Mempublikasikan versi entity',
            self::PrivacyReview => 'Menyetujui field berisi data pribadi (Pejabat PDP)',
            self::MasterDataView => 'Melihat master data (wilayah, orang, pegawai, tahun anggaran)',
            self::MasterDataManage => 'Mengelola master data (orang, pegawai, tahun anggaran, impor wilayah)',
            self::ConsumerApprove => 'Menyetujui aplikasi pemakai entity bersama (Walidata)',
            self::PiiReveal => 'Membuka NIK lengkap (tercatat pii.revealed)',
        };
    }
}
