<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class TextType extends TextualFieldType
{
    public function code(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Teks panjang';
    }

    protected function maxLengthLimit(): int
    {
        return 20000;
    }

    protected function defaultMaxLength(): int
    {
        return 5000;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'Textarea';
    }
}
