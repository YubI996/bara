<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

use App\Shared\Data\DataClassification;

/**
 * Definisi satu field (immutable). Bentuk array-nya disimpan di compiled_schema.fields.
 */
final readonly class FieldDefinition
{
    /**
     * @param  array<string, mixed>  $config  sudah dinormalisasi oleh FieldType
     */
    public function __construct(
        public string $fieldKey,
        public string $code,
        public string $label,
        public ?string $helpText,
        public string $type,
        public bool $required,
        public bool $unique,
        public bool $indexed,
        public bool $searchable,
        public DataClassification $classification,
        public array $config,
        public int $position,
    ) {}

    public function configValue(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field_key' => $this->fieldKey,
            'code' => $this->code,
            'label' => $this->label,
            'help_text' => $this->helpText,
            'type' => $this->type,
            'required' => $this->required,
            'unique' => $this->unique,
            'indexed' => $this->indexed,
            'searchable' => $this->searchable,
            'classification' => $this->classification->value,
            'config' => $this->config,
            'position' => $this->position,
        ];
    }

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $config = $data['config'] ?? [];
        $helpText = $data['help_text'] ?? null;

        return new self(
            fieldKey: self::string($data, 'field_key'),
            code: self::string($data, 'code'),
            label: self::string($data, 'label'),
            helpText: is_string($helpText) ? $helpText : null,
            type: self::string($data, 'type'),
            required: ($data['required'] ?? false) === true,
            unique: ($data['unique'] ?? false) === true,
            indexed: ($data['indexed'] ?? false) === true,
            searchable: ($data['searchable'] ?? false) === true,
            classification: DataClassification::tryFrom(self::string($data, 'classification')) ?? DataClassification::Internal,
            config: is_array($config) ? self::stringKeys($config) : [],
            position: is_int($data['position'] ?? null) ? $data['position'] : 0,
        );
    }

    /** @param  array<mixed>  $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<mixed>  $array
     * @return array<string, mixed>
     */
    private static function stringKeys(array $array): array
    {
        $out = [];
        foreach ($array as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
