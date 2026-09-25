<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Kode identifier platform: huruf kecil, angka, underscore; diawali huruf; 2–63 karakter;
 * bukan kata kunci SQL (docs/05 §4 kontrol 1).
 */
final class Identifier implements ValidationRule
{
    public const string PATTERN = '/^[a-z][a-z0-9_]{1,62}$/';

    /** @var list<string> */
    private const array RESERVED = [
        'all', 'and', 'any', 'array', 'as', 'asc', 'between', 'by', 'case', 'cast', 'check', 'column',
        'constraint', 'create', 'current_date', 'current_user', 'default', 'delete', 'desc', 'distinct',
        'do', 'drop', 'else', 'end', 'except', 'false', 'fetch', 'for', 'foreign', 'from', 'grant', 'group',
        'having', 'in', 'index', 'insert', 'intersect', 'into', 'is', 'join', 'like', 'limit', 'not', 'null',
        'offset', 'on', 'or', 'order', 'primary', 'references', 'returning', 'select', 'session_user',
        'set', 'table', 'then', 'to', 'true', 'union', 'unique', 'update', 'user', 'using', 'values', 'when',
        'where', 'window', 'with',
    ];

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match(self::PATTERN, $value) === 1
            && ! in_array($value, self::RESERVED, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('validation.identifier')->translate();
        }
    }
}
