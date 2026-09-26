<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class RichTextType extends TextualFieldType
{
    public function code(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return 'Teks berformat';
    }

    protected function maxLengthLimit(): int
    {
        return 50000;
    }

    protected function defaultMaxLength(): int
    {
        return 20000;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'RichText';
    }
}
