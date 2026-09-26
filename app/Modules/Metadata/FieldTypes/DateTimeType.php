<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Tanggal & waktu, disimpan ISO 8601 UTC. */
final class DateTimeType extends BaseFieldType
{
    public function code(): string
    {
        return 'datetime';
    }

    public function label(): string
    {
        return 'Tanggal & waktu';
    }

    protected function defaults(): array
    {
        return [];
    }

    public function valueRules(FieldDefinition $field): array
    {
        return ['' => [...$this->presence($field), 'date']];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'format' => 'date-time']);
    }

    public function sqlCast(): string
    {
        return 'timestamptz';
    }

    public function supportsIndex(): bool
    {
        return true;
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'DateTimeInput';
    }
}
