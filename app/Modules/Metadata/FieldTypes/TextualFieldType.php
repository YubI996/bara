<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Basis string/text/rich_text: batas panjang dan (untuk string) pola. */
abstract class TextualFieldType extends BaseFieldType
{
    abstract protected function maxLengthLimit(): int;

    abstract protected function defaultMaxLength(): int;

    protected function defaults(): array
    {
        return ['max_length' => $this->defaultMaxLength()];
    }

    public function configRules(): array
    {
        return [
            'max_length' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxLengthLimit()],
            'default' => ['nullable', 'string', 'max:'.$this->maxLengthLimit()],
        ];
    }

    public function valueRules(FieldDefinition $field): array
    {
        return ['' => [...$this->presence($field), 'string', 'max:'.$this->intConfig($field, 'max_length', $this->defaultMaxLength())]];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'maxLength' => $this->intConfig($field, 'max_length', $this->defaultMaxLength())]);
    }

    public function cast(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function supportsDefault(): bool
    {
        return true;
    }
}
