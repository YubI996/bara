<?php

declare(strict_types=1);

namespace App\Modules\Metadata\FieldTypes;

use App\Modules\Metadata\Contracts\FieldDefinition;

/** Lampiran. Nilai = daftar id di tabel `files` (M2). Ekstensi dibatasi allowlist platform. */
final class FileType extends BaseFieldType
{
    /** @var list<string> */
    public const array ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'csv', 'txt'];

    public const int MAX_KB_LIMIT = 20480;

    public function code(): string
    {
        return 'file';
    }

    public function label(): string
    {
        return 'Berkas lampiran';
    }

    protected function defaults(): array
    {
        return ['max_files' => 1, 'max_kb' => 5120, 'mimes' => ['pdf']];
    }

    public function configRules(): array
    {
        return [
            'mimes' => ['required', 'array', 'min:1'],
            'mimes.*' => ['string', 'in:'.implode(',', self::ALLOWED_EXTENSIONS)],
            'max_kb' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_KB_LIMIT],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function normalizeConfig(array $config): array
    {
        $normalized = parent::normalizeConfig(array_diff_key($config, ['mimes.*' => 1]));

        if (is_array($normalized['mimes'])) {
            $mimes = array_values(array_unique(array_filter($normalized['mimes'], 'is_string')));
            sort($mimes);
            $normalized['mimes'] = $mimes;
        }

        return $normalized;
    }

    public function valueRules(FieldDefinition $field): array
    {
        return [
            '' => [...$this->presence($field), 'array', 'max:'.$this->intConfig($field, 'max_files', 1)],
            '.*' => ['uuid', 'distinct'],
        ];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, [
            'type' => 'array',
            'maxItems' => $this->intConfig($field, 'max_files', 1),
            'items' => ['type' => 'string', 'format' => 'uuid'],
        ]);
    }

    public function uiComponent(FieldDefinition $field): string
    {
        return 'FileUpload';
    }
}
