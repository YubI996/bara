<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

/**
 * Menerima angka format Indonesia ("1.500.000,50") maupun format titik desimal ("1500000.50")
 * dan menormalkannya ke string desimal bertitik tanpa pemisah ribuan.
 */
final class NumberNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $v = str_replace([' ', "\u{00A0}", 'Rp', 'rp', '%'], '', trim($value));

        if ($v === '') {
            return null;
        }

        if (str_contains($v, ',')) {
            // Format Indonesia: titik = ribuan, koma = desimal.
            return str_replace(',', '.', str_replace('.', '', $v));
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v) === 1) {
            // "1.500.000" → ribuan (bukan desimal) karena tiap kelompok tepat 3 digit.
            return str_replace('.', '', $v);
        }

        return $v;
    }
}
