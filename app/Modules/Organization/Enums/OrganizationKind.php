<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/** Jenis unit organisasi internal Pemda. */
enum OrganizationKind: string
{
    case Pemda = 'pemda';
    case Sekretariat = 'sekretariat';
    case Dinas = 'dinas';
    case Badan = 'badan';
    case Kantor = 'kantor';
    case Inspektorat = 'inspektorat';
    case Bagian = 'bagian';
    case Bidang = 'bidang';
    case Subbagian = 'subbagian';
    case Seksi = 'seksi';
    case Uptd = 'uptd';
    case Kecamatan = 'kecamatan';
    case Kelurahan = 'kelurahan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Pemda => 'Pemerintah Daerah',
            self::Sekretariat => 'Sekretariat',
            self::Dinas => 'Dinas',
            self::Badan => 'Badan',
            self::Kantor => 'Kantor',
            self::Inspektorat => 'Inspektorat',
            self::Bagian => 'Bagian',
            self::Bidang => 'Bidang',
            self::Subbagian => 'Subbagian',
            self::Seksi => 'Seksi',
            self::Uptd => 'UPTD',
            self::Kecamatan => 'Kecamatan',
            self::Kelurahan => 'Kelurahan/Desa',
            self::Lainnya => 'Lainnya',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_values(array_map(
            static fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            array_filter(self::cases(), static fn (self $kind): bool => $kind !== self::Pemda),
        ));
    }
}
