<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Wilayah administrasi (Core.Region, kode Kemendagri; data wilayah di-seed pada M4). */
final class RegionType extends BaseFieldType
{
    public function code(): string
    {
        return 'region';
    }

    public function label(): string
    {
        return 'Wilayah administrasi';
    }

    protected function defaults(): array
    {
        return ['level_max' => 4, 'level_min' => 1];
    }

    public function configRules(): array
    {
        return [
            'level_min' => ['nullable', 'integer', 'between:1,4'],
            'level_max' => ['nullable', 'integer', 'between:1,4'],
        ];
    }

    public function configErrors(array $config): array
    {
        $min = $config['level_min'] ?? 1;
        $max = $config['level_max'] ?? 4;

        return is_numeric($min) && is_numeric($max) && (int) $min > (int) $max
            ? ['level_max' => 'Tingkat maksimum harus ≥ tingkat minimum.']
            : [];
    }

    public function valueRules(FieldDefinition $field): array
    {
        return ['' => [...$this->presence($field), 'uuid']];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'format' => 'uuid']);
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'RegionSelect';
    }
}
