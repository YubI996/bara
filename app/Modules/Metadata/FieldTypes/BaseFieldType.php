<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldType;

abstract class BaseFieldType implements FieldType
{
    /** @return array<string, mixed> nilai default config */
    abstract protected function defaults(): array;

    public function configRules(): array
    {
        return [];
    }

    public function normalizeConfig(array $config): array
    {
        $rules = $this->configRules();
        $normalized = $this->defaults();

        foreach ($config as $key => $value) {
            if (! array_key_exists($key, $rules) || $value === null || $value === '') {
                continue;
            }

            // Input form HTML selalu string; samakan agar diff antar-versi tidak palsu ("200" vs 200).
            $normalized[$key] = in_array('integer', $rules[$key], true) && is_numeric($value) ? (int) $value : $value;
        }

        ksort($normalized);

        return $normalized;
    }

    public function configErrors(array $config): array
    {
        return [];
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }

    public function sqlCast(): ?string
    {
        return null;
    }

    public function supportsIndex(): bool
    {
        return false;
    }

    public function supportsSearch(): bool
    {
        return false;
    }

    public function supportsDefault(): bool
    {
        return false;
    }

    /**
     * Awal aturan nilai: wajib atau boleh kosong.
     *
     * @return list<string>
     */
    protected function presence(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable'];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function describe(FieldDefinition $field, array $schema): array
    {
        $schema['title'] = $field->label;

        if ($field->helpText !== null && $field->helpText !== '') {
            $schema['description'] = $field->helpText;
        }

        if ($this->supportsDefault() && array_key_exists('default', $field->config)) {
            $schema['default'] = $field->config['default'];
        }

        if (! $field->required) {
            $schema = ['anyOf' => [$schema, ['type' => 'null']], 'title' => $field->label];
        }

        return $schema;
    }

    /** Ambil config integer (untuk batas yang sudah divalidasi). */
    protected function intConfig(FieldDefinition $field, string $key, int $fallback): int
    {
        $value = $field->configValue($key);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $fallback);
    }

    protected function stringConfig(FieldDefinition $field, string $key): ?string
    {
        $value = $field->configValue($key);

        return is_scalar($value) ? (string) $value : null;
    }
}
