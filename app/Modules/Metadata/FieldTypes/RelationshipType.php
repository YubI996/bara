<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Shared\Validation\Identifier;

/**
 * Relasi ke entity lain. Nilainya TIDAK disimpan di JSONB, tetapi di record_links (ADR 0004).
 * Keberadaan & kewenangan target dicek oleh Action runtime (M3).
 */
final class RelationshipType extends BaseFieldType
{
    public function code(): string
    {
        return 'relationship';
    }

    public function label(): string
    {
        return 'Relasi ke entity lain';
    }

    protected function defaults(): array
    {
        return ['cardinality' => 'many_to_one', 'on_target_delete' => 'restrict'];
    }

    public function configRules(): array
    {
        return [
            'target_entity_id' => ['required', 'uuid'],
            'cardinality' => ['required', 'in:many_to_one,many_to_many'],
            'on_target_delete' => ['nullable', 'in:restrict,nullify'],
            'inverse_code' => ['nullable', 'string', new Identifier],
        ];
    }

    public function valueRules(FieldDefinition $field): array
    {
        if ($this->isMany($field)) {
            return ['' => [...$this->presence($field), 'array', 'max:500'], '.*' => ['uuid', 'distinct']];
        }

        return ['' => [...$this->presence($field), 'uuid']];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $uuid = ['type' => 'string', 'format' => 'uuid'];

        return $this->describe($field, $this->isMany($field) ? ['type' => 'array', 'items' => $uuid, 'uniqueItems' => true] : $uuid);
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'EntitySelector';
    }

    public function isMany(FieldDefinition $field): bool
    {
        return $field->configValue('cardinality') === 'many_to_many';
    }
}
