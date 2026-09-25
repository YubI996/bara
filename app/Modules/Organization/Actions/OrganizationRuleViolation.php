<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use Illuminate\Validation\ValidationException;

/** Pelanggaran aturan bisnis organisasi, dilaporkan ke form sebagai error field. */
final class OrganizationRuleViolation
{
    public static function on(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}
