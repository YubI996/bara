<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class DecimalType extends NumericFieldType
{
    public function code(): string
    {
        return 'decimal';
    }

    public function label(): string
    {
        return 'Angka desimal';
    }

    protected function maxScale(): int
    {
        return 6;
    }

    protected function defaultScale(): int
    {
        return 2;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'NumberInput';
    }
}
