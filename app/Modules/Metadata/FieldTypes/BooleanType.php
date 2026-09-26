<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class BooleanType extends BaseFieldType
{
    public function code(): string
    {
        return 'boolean';
    }

    public function label(): string
    {
        return 'Ya/Tidak';
    }

    protected function defaults(): array
    {
        return [];
    }

    public function configRules(): array
    {
        return ['default' => ['nullable', 'boolean']];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig($config);

        if (array_key_exists('default', $normalized)) {
            $normalized['default'] = filter_var($normalized['default'], FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    public function valueRules(FieldDefinition $field): array
    {
        return ['' => [...$this->presence($field), 'boolean']];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'boolean']);
    }

    public function cast(mixed $value): mixed
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function sqlCast(): string
    {
        return 'boolean';
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
        return 'Checkbox';
    }
}
