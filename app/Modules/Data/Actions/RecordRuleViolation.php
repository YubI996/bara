<?php

declare(strict_types=1);

namespace App\Modules\Data\Actions;

use Illuminate\Validation\ValidationException;

final class RecordRuleViolation
{
    public static function on(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}
