<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

/**
 * Satu langkah migrasi data record akibat publikasi versi (docs/06 §4). Direncanakan modul
 * Metadata, dijalankan modul Data (MigrateRecordData) per batch.
 *
 * kind:
 * - cast        : ubah representasi nilai ke tipe baru (mis. integer → decimal)
 * - wrap_array  : nilai tunggal → array satu elemen (enum → multi_enum)
 * - enum_label  : kode pilihan → label pilihan (enum → string)
 * - backfill    : isi nilai default pada record yang belum punya nilai (field wajib baru)
 */
final readonly class FieldMigration
{
    /**
     * @param  'cast'|'wrap_array'|'enum_label'|'backfill'  $kind
     * @param  array<string, string>  $labels  kode → label (enum_label)
     */
    public function __construct(
        public string $fieldKey,
        public string $kind,
        public string $targetType,
        public mixed $default = null,
        public array $labels = [],
    ) {}

    /** @return array{field_key: string, kind: string, target_type: string, default: mixed, labels: array<string, string>} */
    public function toArray(): array
    {
        return [
            'field_key' => $this->fieldKey,
            'kind' => $this->kind,
            'target_type' => $this->targetType,
            'default' => $this->default,
            'labels' => $this->labels,
        ];
    }

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): ?self
    {
        $kind = $data['kind'] ?? null;
        $labels = [];
        foreach (is_array($data['labels'] ?? null) ? $data['labels'] : [] as $code => $label) {
            if (is_string($label)) {
                $labels[(string) $code] = $label;
            }
        }

        return is_string($data['field_key'] ?? null) && is_string($data['target_type'] ?? null)
            && in_array($kind, ['cast', 'wrap_array', 'enum_label', 'backfill'], true)
            ? new self($data['field_key'], $kind, $data['target_type'], $data['default'] ?? null, $labels)
            : null;
    }
}
