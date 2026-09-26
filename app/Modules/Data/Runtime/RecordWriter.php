<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/** Utilitas tulis bersama Create/UpdateRecord: keunikan, relasi, dan diff audit bermask. */
final readonly class RecordWriter
{
    public function __construct(
        private ConnectionInterface $db,
        private FieldTypeRegistry $types,
    ) {}

    /**
     * Cek nilai unik dengan advisory lock per (entity, field) agar dua transaksi
     * paralel tidak lolos bersamaan sebelum index unik dibuat.
     *
     * @param  array<string, mixed>  $values
     */
    public function assertUnique(EntitySchema $schema, array $values, ?string $exceptId = null): void
    {
        $errors = [];

        foreach ($schema->fields as $field) {
            $value = $values[$field->fieldKey] ?? null;

            if (! $field->unique || $value === null || ! is_scalar($value)) {
                continue;
            }

            $this->db->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["{$schema->entityId}|{$field->fieldKey}"]);

            $exists = $this->db->table('records')
                ->where('entity_id', $schema->entityId)
                ->whereNull('deleted_at')
                ->whereRaw('data @> ?::jsonb', [json_encode([$field->fieldKey => $value], JSON_THROW_ON_ERROR)])
                ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists();

            if ($exists) {
                $errors["data.{$field->code}"] = "{$field->label} sudah dipakai data lain.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<string, list<string>>  $links  field_key => target id */
    public function syncLinks(EntitySchema $schema, string $sourceId, array $links): void
    {
        foreach ($links as $fieldKey => $targets) {
            $relationshipId = $this->db->table('relationships')
                ->where('source_entity_id', $schema->entityId)->where('field_key', $fieldKey)->where('is_active', true)
                ->value('id');

            if (! is_string($relationshipId)) {
                continue;
            }

            $this->db->table('record_links')->where('relationship_id', $relationshipId)->where('source_id', $sourceId)->delete();

            $rows = [];
            foreach ($targets as $position => $targetId) {
                $rows[] = ['relationship_id' => $relationshipId, 'source_id' => $sourceId, 'target_id' => $targetId, 'position' => $position];
            }
            $this->db->table('record_links')->insert($rows);
        }
    }

    /**
     * @return array<string, list<string>> field_key => target id (urut position)
     */
    public function links(EntitySchema $schema, string $sourceId): array
    {
        $rows = $this->db->table('record_links as l')
            ->join('relationships as rel', 'rel.id', '=', 'l.relationship_id')
            ->where('rel.source_entity_id', $schema->entityId)
            ->where('l.source_id', $sourceId)
            ->orderBy('l.position')
            ->get(['rel.field_key', 'l.target_id']);

        $links = [];
        foreach ($rows as $row) {
            if (is_string($row->field_key ?? null) && is_string($row->target_id ?? null)) {
                $links[$row->field_key][] = $row->target_id;
            }
        }

        return $links;
    }

    /**
     * Diff untuk audit, berkunci kode field. Nilai field data pribadi/terbatas tidak pernah
     * disalin ke audit: hanya ditandai berubah (docs/04 §9).
     *
     * @param  array<string, mixed>  $before  berkunci field_key
     * @param  array<string, mixed>  $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(EntitySchema $schema, array $before, array $after): array
    {
        $changes = [];

        foreach ($schema->fields as $field) {
            $old = $before[$field->fieldKey] ?? null;
            $new = $after[$field->fieldKey] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[$field->code] = $this->sensitive($field) ? ['[disamarkan]', '[disamarkan]'] : [$old, $new];
        }

        return $changes;
    }

    private function sensitive(FieldDefinition $field): bool
    {
        return $field->classification->rank() >= 2 || $this->types->get($field->type)->code() === 'rich_text';
    }
}
