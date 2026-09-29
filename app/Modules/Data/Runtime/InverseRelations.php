<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Models\User;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\SchemaRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * Navigasi balik relasi (docs/04 §3 `inverse_code`): record lain yang merujuk record ini,
 * dikelompokkan per relasi dan dibatasi cakupan baca pembaca pada entity sumber.
 * Dua query per relasi aktif (jumlah + contoh), tidak bergantung jumlah tautan.
 */
final readonly class InverseRelations
{
    public const int SAMPLE = 5;

    public function __construct(
        private ConnectionInterface $db,
        private ScopedRecordQuery $query,
        private SchemaRepository $schemas,
        private ScopeFactory $scopes,
    ) {}

    /**
     * @return list<array{relationship: string, label: string, application_code: string, entity_code: string, count: int, items: list<array{id: string, title: string}>}>
     */
    public function for(User $user, EntitySchema $target, string $recordId): array
    {
        $relationships = $this->db->table('relationships')
            ->where('target_entity_id', $target->entityId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'source_entity_id', 'field_key']);

        $groups = [];
        foreach ($relationships as $rel) {
            if (! is_string($rel->id ?? null) || ! is_string($rel->source_entity_id ?? null) || ! is_string($rel->field_key ?? null)) {
                continue;
            }

            $source = $this->schemas->publishedById($rel->source_entity_id);
            if ($source === null || ! $this->scopes->canView($user, $source)) {
                continue;
            }

            $base = fn () => $this->query->records($source->entityId, $this->scopes->read($user, $source))
                ->join('record_links as l', 'l.source_id', '=', 'r.id')
                ->where('l.relationship_id', $rel->id)
                ->where('l.target_id', $recordId);

            $count = $base()->count();
            if ($count === 0) {
                continue;
            }

            $items = [];
            foreach ($base()->orderByDesc('r.created_at')->limit(self::SAMPLE)->get(['r.id', 'r.title']) as $row) {
                if (is_string($row->id ?? null) && is_string($row->title ?? null)) {
                    $items[] = ['id' => $row->id, 'title' => $row->title];
                }
            }

            $field = $source->fieldByKey($rel->field_key);
            $groups[] = [
                'relationship' => $rel->id,
                'label' => $field !== null ? "{$source->entityNamePlural} (lewat {$field->label})" : $source->entityNamePlural,
                'application_code' => $source->applicationCode,
                'entity_code' => $source->entityCode,
                'count' => $count,
                'items' => $items,
            ];
        }

        return $groups;
    }
}
