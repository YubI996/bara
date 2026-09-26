<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class IntegerType extends BaseFieldType
{
    /** Batas aman bilangan bulat JSON/JavaScript. */
    public const int SAFE_LIMIT = 9007199254740991;

    public function code(): string
    {
        return 'integer';
    }

    public function label(): string
    {
        return 'Bilangan bulat';
    }

    protected function defaults(): array
    {
        return [];
    }

    public function configRules(): array
    {
        $range = 'between:-'.self::SAFE_LIMIT.','.self::SAFE_LIMIT;

        return [
            'min' => ['nullable', 'integer', $range],
            'max' => ['nullable', 'integer', $range],
            'default' => ['nullable', 'integer', $range],
        ];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig($config);

        foreach (['min', 'max', 'default'] as $key) {
            if (isset($normalized[$key]) && is_numeric($normalized[$key])) {
                $normalized[$key] = (int) $normalized[$key];
            }
        }

        return $normalized;
    }

    public function configErrors(array $config): array
    {
        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;

        return is_int($min) && is_int($max) && $min > $max
            ? ['max' => 'Nilai maksimum harus lebih besar atau sama dengan minimum.']
            : [];
    }

    public function valueRules(FieldDefinition $field): array
    {
        $rules = [...$this->presence($field), 'integer', 'between:-'.self::SAFE_LIMIT.','.self::SAFE_LIMIT];

        foreach (['min', 'max'] as $key) {
            $value = $field->configValue($key);
            if (is_int($value)) {
                $rules[] = $key.':'.$value;
            }
        }

        return ['' => $rules];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'integer'];
        $min = $field->configValue('min');
        $max = $field->configValue('max');

        if (is_int($min)) {
            $schema['minimum'] = $min;
        }
        if (is_int($max)) {
            $schema['maximum'] = $max;
        }

        return $this->describe($field, $schema);
    }

    public function cast(mixed $value): mixed
    {
        return is_numeric($value) && (string) (int) $value === (string) $value ? (int) $value : $value;
    }

    public function sqlCast(): string
    {
        return 'bigint';
    }

    public function supportsIndex(): bool
    {
        return true;
    }

    public function supportsDefault(): bool
    {
        return true;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'NumberInput';
    }
}
