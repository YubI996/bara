<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Models\User;
use App\Modules\Metadata\Contracts\SchemaRepository;
use Illuminate\Support\Str;

/**
 * Target relasi yang boleh dirujuk user: objek entity target yang terlihat olehnya
 * (record ber-scope, atau entity Core fisik dengan visibilitas internal/publik).
 */
final readonly class RelationTargets
{
    public const int OPTION_LIMIT = 200;

    public function __construct(
        private ScopedRecordQuery $query,
        private SchemaRepository $schemas,
        private ScopeFactory $scopes,
    ) {}

    private function scopeFor(User $user, string $targetEntityId): AccessScope
    {
        $schema = $this->schemas->publishedById($targetEntityId);

        return $schema !== null
            ? $this->scopes->read($user, $schema)
            : AccessScope::read([], $user->kind === 'internal');
    }

    /**
     * @param  list<string>  $ids
     * @return list<string> id yang valid dan terlihat
     */
    public function visible(User $user, string $targetEntityId, array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), fn (string $id): bool => Str::isUuid($id)));

        if ($ids === []) {
            return [];
        }

        $found = $this->query->objects($targetEntityId, $this->scopeFor($user, $targetEntityId))
            ->whereIn('o.id', $ids)
            ->pluck('o.id')
            ->all();

        return array_values(array_filter($found, 'is_string'));
    }

    /**
     * Opsi pilihan (id + judul) untuk form. Pencarian async menyusul di M3.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(User $user, string $targetEntityId): array
    {
        $scope = $this->scopeFor($user, $targetEntityId);
        $schema = $this->schemas->publishedById($targetEntityId);

        $rows = $schema !== null
            ? $this->query->records($targetEntityId, $scope)->orderBy('r.title')->limit(self::OPTION_LIMIT)->get(['o.id', 'r.title'])
            : $this->query->objects($targetEntityId, $scope)
                ->join('core_organizations as c', 'c.id', '=', 'o.id')
                ->whereRaw('(c.valid_to IS NULL OR c.valid_to > CURRENT_DATE)')
                ->orderBy('c.path')->limit(self::OPTION_LIMIT)->get(['o.id', 'c.name as title']);

        $options = [];
        foreach ($rows as $row) {
            if (is_string($row->id ?? null) && is_string($row->title ?? null)) {
                $options[] = ['value' => $row->id, 'label' => $row->title];
            }
        }

        return $options;
    }

    /**
     * Judul untuk sekumpulan id (satu query, tanpa N+1).
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function titles(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), fn (string $id): bool => Str::isUuid($id)));

        if ($ids === []) {
            return [];
        }

        $titles = [];
        foreach ($this->query->titlesFor($ids) as $id => $title) {
            $titles[$id] = $title;
        }

        return $titles;
    }
}
