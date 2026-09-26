<?php

declare(strict_types=1);

namespace App\Modules\Data\Http\Presenters;

use App\Modules\Data\Runtime\FieldGate;
use App\Modules\Data\Runtime\RecordFilters;
use App\Modules\Data\Runtime\RecordValidator;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;

/** Bentuk props Inertia runtime. Semua nilai field lewat FieldGate (CLAUDE.md). */
final readonly class RecordPresenter
{
    private const array LIST_TYPES = ['string', 'integer', 'decimal', 'money', 'percentage', 'boolean', 'date', 'datetime', 'enum'];

    /** Kunci config yang aman & berguna untuk UI (tanpa pola regex mentah dsb.). */
    private const array UI_CONFIG_KEYS = ['max_length', 'min', 'max', 'scale', 'mimes', 'max_files', 'max_kb', 'cardinality', 'max_selected'];

    public function __construct(private FieldTypeRegistry $types) {}

    /** @return array<string, mixed> */
    public function entity(EntitySchema $schema): array
    {
        return [
            'application_code' => $schema->applicationCode,
            'application_name' => $schema->applicationName,
            'code' => $schema->entityCode,
            'name' => $schema->entityName,
            'name_plural' => $schema->entityNamePlural,
            'version' => $schema->version,
        ];
    }

    /**
     * @param  array<string, list<array{value: string, label: string}>>  $relationOptions  kode => opsi
     * @return list<array<string, mixed>>
     */
    public function fields(EntitySchema $schema, FieldGate $gate, array $relationOptions = []): array
    {
        $out = [];

        foreach ($gate->readable($schema->fields) as $field) {
            $config = array_intersect_key($field->config, array_flip(self::UI_CONFIG_KEYS));

            $out[] = [
                'code' => $field->code,
                'label' => $field->label,
                'help_text' => $field->helpText,
                'type' => $field->type,
                'component' => $this->types->get($field->type)->uiComponent($field),
                'required' => $field->required,
                'access' => $gate->access($field),
                'deferred' => in_array($field->type, RecordValidator::DEFERRED_TYPES, true),
                'classification' => $field->classification->value,
                'classification_label' => $field->classification->label(),
                'options' => is_array($field->config['options'] ?? null) ? $field->config['options'] : ($relationOptions[$field->code] ?? []),
                'config' => $config,
                'default' => $field->configValue('default'),
                'in_list' => in_array($field->type, self::LIST_TYPES, true),
                'filterable' => $field->indexed && $gate->access($field) === FieldGate::VISIBLE
                    && in_array($field->type, RecordFilters::FILTERABLE_TYPES, true)
                    && in_array($field->type, ['enum', 'boolean'], true),
            ];
        }

        return $out;
    }

    /**
     * Nilai record berkunci kode field, sudah difilter FieldGate.
     *
     * @param  array<string, mixed>  $data  berkunci field_key
     * @param  array<string, list<string>>  $links  field_key => id target
     * @param  array<string, string>  $titles  id target => judul
     * @param  array<string, array<string, mixed>>  $files  id berkas => info
     * @return array<string, mixed>
     */
    public function values(EntitySchema $schema, FieldGate $gate, array $data, array $links = [], array $titles = [], array $files = [], bool $listOnly = false): array
    {
        $values = [];
        $listed = 0;

        foreach ($gate->readable($schema->fields) as $field) {
            if ($listOnly && (! in_array($field->type, self::LIST_TYPES, true) || $listed >= 4)) {
                continue;
            }
            $listed++;

            $values[$field->code] = $gate->present($field, $this->raw($field, $data, $links, $titles, $files));
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $links
     * @param  array<string, string>  $titles
     * @param  array<string, array<string, mixed>>  $files
     */
    private function raw(FieldDefinition $field, array $data, array $links, array $titles, array $files): mixed
    {
        if ($field->type === 'relationship') {
            return array_map(
                fn (string $id): array => ['value' => $id, 'label' => $titles[$id] ?? '(tidak tersedia)'],
                $links[$field->fieldKey] ?? [],
            );
        }

        $value = $data[$field->fieldKey] ?? null;

        if ($field->type === 'file') {
            $out = [];
            foreach (is_array($value) ? $value : [] as $id) {
                if (is_string($id) && isset($files[$id])) {
                    $out[] = $files[$id];
                }
            }

            return $out;
        }

        return $value;
    }
}
