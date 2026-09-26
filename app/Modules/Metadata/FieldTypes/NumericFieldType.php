<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;
use Brick\Math\BigDecimal;

/**
 * Basis angka presisi (decimal, money, percentage). Nilai disimpan sebagai STRING di JSONB
 * agar tidak kehilangan presisi floating point, lalu di-cast ::numeric di SQL (docs/06 §1).
 */
abstract class NumericFieldType extends BaseFieldType
{
    abstract protected function maxScale(): int;

    abstract protected function defaultScale(): int;

    protected function defaults(): array
    {
        return ['scale' => $this->defaultScale()];
    }

    public function configRules(): array
    {
        return [
            'scale' => ['nullable', 'integer', 'min:0', 'max:'.$this->maxScale()],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
            'default' => ['nullable', 'numeric'],
        ];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig($config);

        foreach (['min', 'max', 'default'] as $key) {
            if (isset($normalized[$key]) && is_numeric($normalized[$key])) {
                $normalized[$key] = (string) $normalized[$key];
            }
        }

        return $normalized;
    }

    public function configErrors(array $config): array
    {
        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;

        if (is_numeric($min) && is_numeric($max) && BigDecimal::of((string) $min)->isGreaterThan((string) $max)) {
            return ['max' => 'Nilai maksimum harus lebih besar atau sama dengan minimum.'];
        }

        return [];
    }

    public function valueRules(FieldDefinition $field): array
    {
        $scale = $this->intConfig($field, 'scale', $this->defaultScale());
        $rules = [...$this->presence($field), 'numeric', 'decimal:0,'.$scale];

        foreach (['min' => 'min', 'max' => 'max'] as $key => $rule) {
            $value = $this->stringConfig($field, $key);
            if ($value !== null) {
                $rules[] = $rule.':'.$value;
            }
        }

        return ['' => $rules];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        // Angka presisi dikirim sebagai string desimal.
        $scale = $this->intConfig($field, 'scale', $this->defaultScale());
        $pattern = $scale === 0 ? '^-?\d+$' : '^-?\d+(\.\d{1,'.$scale.'})?$';

        return $this->describe($field, ['type' => 'string', 'pattern' => $pattern]);
    }

    public function cast(mixed $value): mixed
    {
        return is_numeric($value) ? (string) $value : $value;
    }

    public function sqlCast(): string
    {
        return 'numeric';
    }

    public function supportsIndex(): bool
    {
        return true;
    }

    public function supportsDefault(): bool
    {
        return true;
    }
}
