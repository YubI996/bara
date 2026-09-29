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
    private const array LIST_TYPES = ['string', 'integer', 'decimal', 'money', 'percentage', 'boolean', 'date', 'datetime', 'enum', 'relationship'];

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
     * Opsi relasi tidak dikirim di sini: EntitySelector mencarinya lewat endpoint lookup.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(EntitySchema $schema, FieldGate $gate): array
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
                'options' => is_array($field->config['options'] ?? null) ? $field->config['options'] : [],
                'config' => $config,
                'default' => $field->configValue('default'),
                'in_list' => in_array($field->type, self::LIST_TYPES, true),
                'lookup_url' => $field->type === 'relationship'
                    ? route('runtime.lookup', ['app' => $schema->applicationCode, 'entity' => $schema->entityCode, 'field' => $field->code], false)
                    : null,
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
     * @param  array<string, string>  $urls  id target => URL detail (hanya yang terlihat)
     * @return array<string, mixed>
     */
    public function values(EntitySchema $schema, FieldGate $gate, array $data, array $links = [], array $titles = [], array $files = [], bool $listOnly = false, array $urls = []): array
    {
        $values = [];
        $listed = 0;

        foreach ($gate->readable($schema->fields) as $field) {
            if ($listOnly && (! in_array($field->type, self::LIST_TYPES, true) || $listed >= 4)) {
                continue;
            }
            $listed++;

            $values[$field->code] = $gate->present($field, $this->raw($field, $data, $links, $titles, $files, $urls));
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $links
     * @param  array<string, string>  $titles
     * @param  array<string, array<string, mixed>>  $files
     * @param  array<string, string>  $urls
     */
    private function raw(FieldDefinition $field, array $data, array $links, array $titles, array $files, array $urls): mixed
    {
        if ($field->type === 'relationship') {
            return array_map(
                fn (string $id): array => ['value' => $id, 'label' => $titles[$id] ?? '(tidak tersedia)', 'url' => $urls[$id] ?? null],
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
