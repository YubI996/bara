<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/**
 * Basis enum/multi_enum dengan daftar opsi tetap. Codelist bersama menyusul di M4.
 */
abstract class ChoiceFieldType extends BaseFieldType
{
    public const string VALUE_PATTERN = '/^[a-z0-9][a-z0-9_]{0,62}$/';

    public const int MAX_OPTIONS = 200;

    protected function defaults(): array
    {
        return ['options' => []];
    }

    public function configRules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
            'options.*.value' => ['required', 'string', 'regex:'.self::VALUE_PATTERN],
            'options.*.label' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig(array_diff_key($config, ['options.*.value' => 1, 'options.*.label' => 1]));
        $options = [];

        foreach (is_array($config['options'] ?? null) ? $config['options'] : [] as $option) {
            if (is_array($option) && is_string($option['value'] ?? null) && is_string($option['label'] ?? null)) {
                $options[] = ['value' => $option['value'], 'label' => trim($option['label'])];
            }
        }

        $normalized['options'] = $options;

        return $normalized;
    }

    public function configErrors(array $config): array
    {
        $values = array_column($this->options($config), 'value');
        $labels = array_map('mb_strtolower', array_column($this->options($config), 'label'));

        if (count($values) !== count(array_unique($values))) {
            return ['options' => 'Nilai opsi harus unik.'];
        }
        if (count($labels) !== count(array_unique($labels))) {
            return ['options' => 'Label opsi harus unik agar bisa dibedakan pembaca layar.'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{value: string, label: string}>
     */
    protected function options(array $config): array
    {
        $options = [];
        foreach (is_array($config['options'] ?? null) ? $config['options'] : [] as $option) {
            if (is_array($option) && is_string($option['value'] ?? null) && is_string($option['label'] ?? null)) {
                $options[] = ['value' => $option['value'], 'label' => $option['label']];
            }
        }

        return $options;
    }

    /** @return list<string> */
    protected function optionValues(FieldDefinition $field): array
    {
        return array_column($this->options($field->config), 'value');
    }
}
