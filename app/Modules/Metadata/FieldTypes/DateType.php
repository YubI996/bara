<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Tanggal YYYY-MM-DD. Batas min/max boleh tanggal tetap atau `today`. */
final class DateType extends BaseFieldType
{
    private const string BOUND = 'regex:/^(today|\d{4}-\d{2}-\d{2})$/';

    public function code(): string
    {
        return 'date';
    }

    public function label(): string
    {
        return 'Tanggal';
    }

    protected function defaults(): array
    {
        return [];
    }

    public function configRules(): array
    {
        return [
            'min' => ['nullable', 'string', self::BOUND],
            'max' => ['nullable', 'string', self::BOUND],
            'default' => ['nullable', 'string', self::BOUND],
        ];
    }

    public function configErrors(array $config): array
    {
        $errors = [];
        foreach (['min', 'max', 'default'] as $key) {
            $value = $config[$key] ?? null;
            if (is_string($value) && $value !== 'today' && ! $this->isRealDate($value)) {
                $errors[$key] = 'Tanggal tidak valid.';
            }
        }

        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;
        if ($errors === [] && is_string($min) && is_string($max) && $min !== 'today' && $max !== 'today' && $min > $max) {
            $errors['max'] = 'Tanggal maksimum harus sama atau setelah tanggal minimum.';
        }

        return $errors;
    }

    public function valueRules(FieldDefinition $field): array
    {
        $rules = [...$this->presence($field), 'date_format:Y-m-d'];

        if (($min = $this->stringConfig($field, 'min')) !== null) {
            $rules[] = 'after_or_equal:'.$min;
        }
        if (($max = $this->stringConfig($field, 'max')) !== null) {
            $rules[] = 'before_or_equal:'.$max;
        }

        return ['' => $rules];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'format' => 'date']);
    }

    public function sqlCast(): string
    {
        return 'date';
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
        return 'DateInput';
    }

    private function isRealDate(string $value): bool
    {
        $parts = explode('-', $value);

        return count($parts) === 3 && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }
}
