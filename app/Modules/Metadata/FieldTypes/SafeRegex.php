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

        // Grup berkuantifier yang berisi kuantifier, alternasi, atau grup lain — (a+)+, ((a+))+,
        // (a|aa)+ — adalah sumber catastrophic backtracking.
        if (self::hasRiskyQuantifiedGroup($pattern)) {
            return 'Pola mengandung kuantifier bersarang atau alternasi berulang yang berisiko memperlambat server.';
        }

        if (preg_match('/\\\\[1-9]|\\\\k</', $pattern) === 1) {
            return 'Backreference tidak diizinkan.';
        }

        if (@preg_match(self::delimited($pattern), '') === false) {
            return 'Pola regex tidak valid.';
        }

        if (self::backtracksExcessively(self::delimited($pattern))) {
            return 'Pola terlalu lambat diuji terhadap input panjang. Sederhanakan polanya.';
        }

        return null;
    }

    private static function hasRiskyQuantifiedGroup(string $pattern): bool
    {
        /** @var list<bool> $stack true = isi grup berisiko bila grup ini diulang */
        $stack = [];
        $inClass = false;
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }
            if ($inClass) {
                $inClass = $char !== ']';

                continue;
            }

            switch ($char) {
                case '[':
                    $inClass = true;
                    break;
                case '(':
                    if ($stack !== []) {
                        $stack[array_key_last($stack)] = true;
                    }
                    $stack[] = false;
                    break;
                case '|':
                case '+':
                case '*':
                case '{':
                    if ($stack !== []) {
                        $stack[array_key_last($stack)] = true;
                    }
                    break;
                case ')':
                    $risky = array_pop($stack) ?? false;
                    $next = $pattern[$i + 1] ?? '';
                    // Pengulangan terbatas ({2}, {1,3}) aman; yang berbahaya tak terbatas: +, *, {n,}.
                    $unbounded = $next === '+' || $next === '*'
                        || ($next === '{' && preg_match('/^\{\d+,\}/', substr($pattern, $i + 1)) === 1);
                    if ($risky && $unbounded) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    /** Uji empiris: pola dijalankan pada input adversarial pendek dengan batas backtrack rendah. */
    private static function backtracksExcessively(string $regex): bool
    {
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '100000');

        try {
            foreach (['a', '0', ' ', 'aA0', 'x.'] as $unit) {
                $subject = str_repeat($unit, 40).'!';
                @preg_match($regex, $subject);
                if (in_array(preg_last_error(), [PREG_BACKTRACK_LIMIT_ERROR, PREG_RECURSION_LIMIT_ERROR, PREG_JIT_STACKLIMIT_ERROR], true)) {
                    return true;
                }
            }
        } finally {
            ini_set('pcre.backtrack_limit', $previous === false ? '1000000' : $previous);
        }

        return false;
    }
}
