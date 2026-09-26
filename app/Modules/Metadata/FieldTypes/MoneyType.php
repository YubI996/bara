<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Rupiah, 2 desimal tetap, minimal 0 secara default. */
final class MoneyType extends NumericFieldType
{
    public function code(): string
    {
        return 'money';
    }

    public function label(): string
    {
        return 'Uang (Rupiah)';
    }

    protected function maxScale(): int
    {
        return 2;
    }

    protected function defaultScale(): int
    {
        return 2;
    }

    protected function defaults(): array
    {
        return ['min' => '0', 'scale' => 2];
    }

    public function configRules(): array
    {
        $rules = parent::configRules();
        unset($rules['scale']);

        return $rules;
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig($config);
        $normalized['scale'] = 2;

        return $normalized;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'MoneyInput';
    }
}
