<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Shared\Validation\Identifier;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * Validasi satu field secara mandiri: kode, config, default, dan kombinasi flag.
 * Dipakai saat field disimpan ke draft (umpan balik cepat) dan saat publikasi (pertahanan akhir).
 */
final readonly class FieldConfigValidator
{
    /** Kode yang dicadangkan untuk kolom/atribut sistem. */
    public const array RESERVED_CODES = [
        'id', 'uuid', 'data', 'title', 'search', 'entity', 'entity_id', 'entity_version_id', 'owner',
        'owner_org_id', 'owner_path', 'visibility', 'created_at', 'updated_at', 'deleted_at',
        'created_by', 'updated_by', 'lock_version', 'revision_of', 'state', 'status_workflow',
    ];

    public function __construct(
        private FieldTypeRegistry $types,
        private ValidatorFactory $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $config  config mentah dari form
     * @return array{config: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(FieldDefinition $field, array $config): array
    {
        $errors = [];

        if (! Identifier::isValid($field->code)) {
            $errors['code'] = __('validation.identifier', ['attribute' => 'Kode']);
        } elseif (in_array($field->code, self::RESERVED_CODES, true)) {
            $errors['code'] = "Kode \"{$field->code}\" dicadangkan untuk sistem. Pakai kode lain.";
        }

        if (! $this->types->has($field->type)) {
            return ['config' => [], 'errors' => [...$errors, 'type' => 'Tipe field tidak dikenal.']];
        }

        $type = $this->types->get($field->type);
        $check = $this->validator->make($config, $type->configRules());

        if ($check->fails()) {
            foreach ($check->errors()->messages() as $key => $messages) {
                $errors['config.'.$key] = $messages[0] ?? 'Tidak valid.';
            }

            return ['config' => [], 'errors' => $errors];
        }

        $normalized = $type->normalizeConfig($config);

        foreach ($type->configErrors($normalized) as $key => $message) {
            $errors['config.'.$key] = $message;
        }

        if (array_key_exists('default', $normalized) && ! isset($errors['config.default'])) {
            $probe = new FieldDefinition($field->fieldKey, $field->code, $field->label, null, $field->type, true, false, false, false, $field->classification, $normalized, 0);
            $defaultCheck = $this->validator->make(['v' => $normalized['default']], $this->prefix($type->valueRules($probe)));
            if ($defaultCheck->fails()) {
                $errors['config.default'] = 'Nilai default tidak sesuai aturan field ini.';
            }
        }

        if ($field->indexed && ! $type->supportsIndex()) {
            $errors['is_indexed'] = "Tipe {$type->label()} tidak bisa diindeks.";
        }
        if ($field->unique && ! $field->indexed) {
            $errors['is_unique'] = 'Field unik harus diindeks.';
        }
        if ($field->searchable && ! $type->supportsSearch()) {
            $errors['is_searchable'] = "Tipe {$type->label()} tidak mendukung pencarian teks.";
        }

        return ['config' => $normalized, 'errors' => $errors];
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    private function prefix(array $rules): array
    {
        $out = [];
        foreach ($rules as $suffix => $list) {
            $out['v'.$suffix] = $list;
        }

        return $out;
    }
}
