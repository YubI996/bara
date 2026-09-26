<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

/**
 * Pemeriksa pola regex buatan admin (docs/05 §4: cegah ReDoS).
 * Menolak pola yang tidak valid, terlalu panjang, backreference, dan kuantifier bersarang.
 */
final class SafeRegex
{
    public const int MAX_LENGTH = 200;

    public static function delimited(string $pattern): string
    {
        return '/^(?:'.str_replace('/', '\/', $pattern).')$/u';
    }

    public static function error(string $pattern): ?string
    {
        if (mb_strlen($pattern) > self::MAX_LENGTH) {
            return 'Pola maksimal '.self::MAX_LENGTH.' karakter.';
        }

        // Kuantifier bersarang seperti (a+)+, (a*)*, (a|aa)+ adalah sumber catastrophic backtracking.
        if (preg_match('/\((?:[^()\\\\]|\\\\.)*[+*}](?:[^()\\\\]|\\\\.)*\)\s*[+*{]/', $pattern) === 1) {
            return 'Pola mengandung kuantifier bersarang yang berisiko memperlambat server.';
        }

        if (preg_match('/\\\\[1-9]|\\\\k</', $pattern) === 1) {
            return 'Backreference tidak diizinkan.';
        }

        if (@preg_match(self::delimited($pattern), '') === false) {
            return 'Pola regex tidak valid.';
        }

        return null;
    }
}
