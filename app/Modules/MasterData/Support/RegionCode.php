<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Support;

/**
 * Kode wilayah Kemendagri: 11 (provinsi), 11.01 (kab/kota), 11.01.01 (kecamatan),
 * 11.01.01.2001 (desa/kelurahan). Level dan induk diturunkan dari kode.
 */
final class RegionCode
{
    public const string PATTERN = '/^\d{2}(\.\d{2}(\.\d{2}(\.\d{4})?)?)?$/';

    public const array LEVEL_LABELS = [1 => 'Provinsi', 2 => 'Kabupaten/Kota', 3 => 'Kecamatan', 4 => 'Desa/Kelurahan'];

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    public static function level(string $code): int
    {
        return substr_count($code, '.') + 1;
    }

    public static function parent(string $code): ?string
    {
        $pos = strrpos($code, '.');

        return $pos === false ? null : substr($code, 0, $pos);
    }
}
