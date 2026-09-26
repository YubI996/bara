<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Persentase 0–100. */
final class PercentageType extends NumericFieldType
{
    public function code(): string
    {
        return 'percentage';
    }

    public function label(): string
    {
        return 'Persentase';
    }

    protected function maxScale(): int
    {
        return 4;
    }

    protected function defaultScale(): int
    {
        return 2;
    }

    protected function defaults(): array
    {
        return ['max' => '100', 'min' => '0', 'scale' => 2];
    }

    public function configRules(): array
    {
        return [
            'scale' => ['nullable', 'integer', 'min:0', 'max:4'],
            'default' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig($config);
        $normalized['min'] = '0';
        $normalized['max'] = '100';
        ksort($normalized);

        return $normalized;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'PercentInput';
    }
}
