<?php

declare(strict_types=1);

namespace App\Modules\Data\Jobs;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Runtime\AccessScope;
use App\Modules\Data\Runtime\JsonData;
use App\Modules\Data\Runtime\ScopedRecordQuery;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\FieldMigration;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Migrasi data record setelah publikasi versi (docs/06 §4): konversi tipe kompatibel dan
 * backfill default field wajib baru. Hanya menyentuh record dari versi lama (entity_version_id
 * berbeda), sehingga record yang sudah ditulis dengan skema baru tidak dikonversi dua kali.
 * Idempoten: aman dijalankan ulang.
 */
final class MigrateRecordData implements ShouldQueue
{
    use Queueable;

    public const int BATCH = 1000;

    public int $timeout = 3600;

    /** @param  list<array<mixed>>  $migrations  FieldMigration::toArray() */
    public function __construct(
        public string $entityId,
        public string $entityVersionId,
        public array $migrations,
    ) {}

    public function handle(ConnectionInterface $db, ScopedRecordQuery $query, FieldTypeRegistry $types, AuditLogger $audit, EventRecorder $events): void
    {
        $steps = array_values(array_filter(array_map(FieldMigration::fromArray(...), $this->migrations)));
        $steps = array_values(array_filter($steps, fn (FieldMigration $s): bool => Str::isUuid($s->fieldKey) && $types->has($s->targetType)));

        if ($steps === [] || ! Str::isUuid($this->entityId) || ! Str::isUuid($this->entityVersionId)) {
            return;
        }

        $migrated = 0;
        $failed = [];

        // Sistem: job internal tanpa user; scope penuh (SystemContext, CLAUDE.md).
        $query->records($this->entityId, AccessScope::system())
            ->where('r.entity_version_id', '<>', $this->entityVersionId)
            ->select(['r.id', 'r.data'])
            ->chunkById(self::BATCH, function ($rows) use ($db, $steps, $types, &$migrated, &$failed): void {
                $db->transaction(function () use ($db, $rows, $steps, $types, &$migrated, &$failed): void {
                    foreach ($rows as $row) {
                        if (! is_string($row->id ?? null)) {
                            continue;
                        }

                        $data = JsonData::decode($row->data ?? null);
                        $next = $this->apply($data, $steps, $types, $row->id, $failed);
                        if ($next === $data) {
                            continue;
                        }

                        $db->table('records')->where('id', $row->id)->update([
                            'data' => json_encode((object) $next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            'entity_version_id' => $this->entityVersionId,
                            'lock_version' => $db->raw('lock_version + 1'),
                            'updated_at' => now(),
                        ]);
                        $migrated++;
                    }
                });
            }, 'r.id', 'id');

        $db->transaction(function () use ($audit, $events, $steps, $migrated, $failed): void {
            $audit->log('records.migrated', $this->entityId, 'metadata.entity', context: [
                'entity_version_id' => $this->entityVersionId,
                'steps' => count($steps),
                'records' => $migrated,
                'failed' => count($failed),
            ]);
            $events->record('records.migrated', 'metadata.entity', $this->entityId, [
                'entity_version_id' => $this->entityVersionId,
                'records' => $migrated,
                'failed_record_ids' => array_slice($failed, 0, 100),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<FieldMigration>  $steps
     * @param  list<string>  $failed
     * @return array<string, mixed>
     */
    private function apply(array $data, array $steps, FieldTypeRegistry $types, string $recordId, array &$failed): array
    {
        foreach ($steps as $step) {
            $key = $step->fieldKey;
            $value = $data[$key] ?? null;
            $type = $types->get($step->targetType);

            switch ($step->kind) {
                case 'backfill':
                    if ($value === null || $value === '' || $value === []) {
                        $data[$key] = $type->cast($step->default);
                    }
                    break;
                case 'wrap_array':
                    if (is_string($value) && $value !== '') {
                        $data[$key] = [$value];
                    }
                    break;
                case 'enum_label':
                    if (is_string($value)) {
                        $data[$key] = $step->labels[$value] ?? $value;
                    }
                    break;
                case 'cast':
                    if ($value !== null) {
                        $cast = $type->cast($value);
                        if (! is_scalar($cast)) {
                            $failed[] = $recordId;
                            break;
                        }
                        $data[$key] = $cast;
                    }
                    break;
            }
        }

        return $data;
    }
}
