<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Modules\Metadata\Contracts\EntitySchema;
use Illuminate\Database\Query\Builder;

/**
 * Filter daftar record. Kesetaraan memakai containment JSONB (`data @> ?::jsonb`) yang dilayani
 * index GIN `records_data_gin` — tanpa SQL dinamis; kunci (field_key) dan nilai lewat binding.
 */
final class RecordFilters
{
    /** Tipe yang bisa difilter kesetaraan; tipe JSON harus sama dengan hasil FieldType::cast. */
    public const array FILTERABLE_TYPES = ['enum', 'string', 'boolean', 'integer'];

    public function search(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereRaw("r.search @@ plainto_tsquery('simple', ?)", [$term])
            ->orWhere('r.title', 'ilike', '%'.addcslashes($term, '%_\\').'%'));
    }

    /**
     * @param  array<mixed>  $input  f[kode] => nilai
     * @return array<string, string> filter yang benar-benar diterapkan
     */
    public function apply(Builder $query, EntitySchema $schema, FieldGate $gate, array $input): array
    {
        $applied = [];

        foreach ($schema->fields as $field) {
            $value = $input[$field->code] ?? null;

            if (! is_string($value) || $value === '' || ! $field->indexed
                || ! in_array($field->type, self::FILTERABLE_TYPES, true)
                || $gate->access($field) !== FieldGate::VISIBLE) {
                continue;
            }

            $binding = match ($field->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => preg_match('/^-?\d{1,18}$/', $value) === 1 ? (int) $value : null,
                default => $value,
            };

            if ($binding === null) {
                continue;
            }

            $query->whereRaw('r.data @> ?::jsonb', [json_encode([$field->fieldKey => $binding], JSON_THROW_ON_ERROR)]);
            $applied[$field->code] = $value;
        }

        return $applied;
    }
}
