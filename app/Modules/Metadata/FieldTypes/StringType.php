<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class StringType extends TextualFieldType
{
    public function code(): string
    {
        return 'string';
    }

    public function label(): string
    {
        return 'Teks singkat';
    }

    protected function maxLengthLimit(): int
    {
        return 500;
    }

    protected function defaultMaxLength(): int
    {
        return 255;
    }

    public function configRules(): array
    {
        return [...parent::configRules(), 'pattern' => ['nullable', 'string', 'max:'.SafeRegex::MAX_LENGTH]];
    }

    public function configErrors(array $config): array
    {
        $pattern = $config['pattern'] ?? null;

        if (is_string($pattern) && ($error = SafeRegex::error($pattern)) !== null) {
            return ['pattern' => $error];
        }

        return [];
    }

    public function valueRules(FieldDefinition $field): array
    {
        $rules = parent::valueRules($field);
        $pattern = $this->stringConfig($field, 'pattern');

        if ($pattern !== null) {
            $rules[''][] = 'regex:'.SafeRegex::delimited($pattern);
        }

        return $rules;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $pattern = $this->stringConfig($field, 'pattern');
        $schema = ['type' => 'string', 'maxLength' => $this->intConfig($field, 'max_length', 255)];

        if ($pattern !== null) {
            $schema['pattern'] = '^(?:'.$pattern.')$';
        }

        return $this->describe($field, $schema);
    }

    public function sqlCast(): string
    {
        return 'text';
    }

    public function supportsIndex(): bool
    {
        return true;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'TextInput';
    }
}
