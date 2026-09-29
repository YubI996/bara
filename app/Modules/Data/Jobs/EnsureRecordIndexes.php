<?php

declare(strict_types=1);

namespace App\Modules\Data\Jobs;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Contracts\SchemaRepository;
use App\Shared\Validation\Identifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Menyelaraskan index ekspresi per field `is_indexed` (docs/04 §4, ADR 0014).
 * DDL tidak bisa memakai binding, jadi setiap identifier/literal divalidasi ketat sebelum disisipkan.
 */
final class EnsureRecordIndexes implements ShouldQueue
{
    use Queueable;

    /** Fungsi cast IMMUTABLE per sqlCast FieldType. */
    private const array CAST_FUNCTIONS = [
        'bigint' => 'bara_to_bigint',
        'numeric' => 'bara_to_numeric',
        'date' => 'bara_to_date',
        'timestamptz' => 'bara_to_timestamptz',
        'boolean' => 'bara_to_boolean',
    ];

    public function __construct(public string $entityId) {}

    public function handle(ConnectionInterface $db, SchemaRepository $schemas, AuditLogger $audit): void
    {
        $schema = $schemas->publishedById($this->entityId);

        if ($schema === null || ! Str::isUuid($this->entityId)) {
            return;
        }

        $concurrently = config()->boolean('bara.records.concurrent_index', true) ? 'CONCURRENTLY ' : '';
        $wanted = [];

        foreach ($schema->fields as $field) {
            if (! $field->indexed || ! Str::isUuid($field->fieldKey)) {
                continue;
            }

            $cast = match ($field->type) {
                'integer' => 'bigint',
                'decimal', 'money', 'percentage' => 'numeric',
                'date' => 'date',
                'datetime' => 'timestamptz',
                'boolean' => 'boolean',
                default => null,
            };
            $name = 'rf_'.substr(hash('sha256', "{$this->entityId}|{$field->fieldKey}|{$cast}|".($field->unique ? 'u' : '')), 0, 24);
            $wanted[] = $name;

            if (! Identifier::isValid($name)) {
                continue;
            }

            $accessor = "(data->>'{$field->fieldKey}')";
            $expression = $cast === null ? $accessor : self::CAST_FUNCTIONS[$cast].$accessor;

            try {
                $db->statement(sprintf(
                    "CREATE %sINDEX %sIF NOT EXISTS %s ON records ((%s)) WHERE entity_id = '%s' AND deleted_at IS NULL",
                    $field->unique ? 'UNIQUE ' : '',
                    $concurrently,
                    $name,
                    $expression,
                    $this->entityId,
                ));
            } catch (QueryException $e) {
                // Mis. nilai ganda saat membuat index unik: catat, jangan gagalkan index lain.
                $audit->log('metadata.index_failed', $this->entityId, 'metadata.entity', context: [
                    'field' => $field->code,
                    'sqlstate' => (string) $e->getCode(),
                ]);
            }
        }

        // Hapus index milik entity ini yang tidak lagi diinginkan.
        $existing = $db->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'records' AND indexname LIKE 'rf\\_%' AND indexdef LIKE ?",
            ['%entity_id = '."'{$this->entityId}'".'%'],
        );

        foreach ($existing as $row) {
            $name = is_object($row) && isset($row->indexname) && is_string($row->indexname) ? $row->indexname : null;
            if ($name !== null && ! in_array($name, $wanted, true) && Identifier::isValid($name)) {
                $db->statement("DROP INDEX {$concurrently}IF EXISTS {$name}");
            }
        }

        $this->syncManyToOneIndexes($db, $audit, $concurrently);
    }

    /**
     * docs/04 §4: satu tautan per source untuk relasi many_to_one, dijaga partial unique index
     * per relationship (lapis DB di bawah RecordWriter::syncLinks).
     */
    private function syncManyToOneIndexes(ConnectionInterface $db, AuditLogger $audit, string $concurrently): void
    {
        $relationships = $db->table('relationships')->where('source_entity_id', $this->entityId)
            ->get(['id', 'code', 'cardinality', 'is_active']);

        foreach ($relationships as $rel) {
            $id = is_string($rel->id ?? null) && Str::isUuid($rel->id) ? $rel->id : null;
            if ($id === null) {
                continue;
            }

            $name = 'rl_m2o_'.substr(hash('sha256', $id), 0, 24);
            $wanted = ($rel->is_active ?? false) === true && ($rel->cardinality ?? null) === 'many_to_one';

            try {
                $db->statement($wanted
                    ? "CREATE UNIQUE INDEX {$concurrently}IF NOT EXISTS {$name} ON record_links (source_id) WHERE relationship_id = '{$id}'"
                    : "DROP INDEX {$concurrently}IF EXISTS {$name}");
            } catch (QueryException $e) {
                $audit->log('metadata.index_failed', $this->entityId, 'metadata.entity', context: [
                    'relationship' => is_string($rel->code ?? null) ? $rel->code : $id,
                    'sqlstate' => (string) $e->getCode(),
                ]);
            }
        }
    }
}
