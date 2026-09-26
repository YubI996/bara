<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use Illuminate\Validation\ValidationException;

final class MetadataRuleViolation
{
    /** @param  string|list<string>  $messages */
    public static function on(string $field, string|array $messages): ValidationException
    {
        return ValidationException::withMessages([$field => $messages]);
    }
}
