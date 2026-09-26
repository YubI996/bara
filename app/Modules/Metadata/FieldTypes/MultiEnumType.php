<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

final class MultiEnumType extends ChoiceFieldType
{
    public function code(): string
    {
        return 'multi_enum';
    }

    public function label(): string
    {
        return 'Pilihan ganda';
    }

    public function configRules(): array
    {
        return [...parent::configRules(), 'max_selected' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_OPTIONS]];
    }

    public function valueRules(FieldDefinition $field): array
    {
        $rules = [...$this->presence($field), 'array'];

        if (is_int($max = $field->configValue('max_selected'))) {
            $rules[] = 'max:'.$max;
        }

        return [
            '' => $rules,
            '.*' => ['string', 'distinct', 'in:'.implode(',', $this->optionValues($field))],
        ];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'string', 'enum' => $this->optionValues($field)]];

        if (is_int($max = $field->configValue('max_selected'))) {
            $schema['maxItems'] = $max;
        }

        return $this->describe($field, $schema);
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'CheckboxGroup';
    }
}
