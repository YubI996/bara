<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class EnumType extends ChoiceFieldType
{
    public function code(): string
    {
        return 'enum';
    }

    public function label(): string
    {
        return 'Pilihan tunggal';
    }

    public function configRules(): array
    {
        return [...parent::configRules(), 'default' => ['nullable', 'string']];
    }

    public function configErrors(array $config): array
    {
        $errors = parent::configErrors($config);
        $default = $config['default'] ?? null;

        if ($errors === [] && is_string($default) && ! in_array($default, array_column($this->options($config), 'value'), true)) {
            $errors['default'] = 'Nilai default harus salah satu opsi.';
        }

        return $errors;
    }

    public function valueRules(FieldDefinition $field): array
    {
        // Nilai opsi sudah dibatasi VALUE_PATTERN (tanpa koma), aman untuk aturan `in:`.
        return ['' => [...$this->presence($field), 'string', 'in:'.implode(',', $this->optionValues($field))]];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'enum' => $this->optionValues($field)]);
    }

    public function sqlCast(): string
    {
        return 'text';
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
        return count($this->optionValues($field)) <= 5 ? 'RadioGroup' : 'Select';
    }
}
