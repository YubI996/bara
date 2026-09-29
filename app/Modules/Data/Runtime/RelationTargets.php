<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Models\User;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\SchemaRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Target relasi yang boleh dirujuk user: objek entity target yang terlihat olehnya
 * (record ber-scope, atau entity Core fisik dengan visibilitas internal/publik).
 */
final readonly class RelationTargets
{
    public const int SEARCH_LIMIT = 20;

    public const int MIN_TERM_LENGTH = 2;

    public const string OUT_OF_SCOPE_LABEL = '(di luar kewenangan Anda)';

    public function __construct(
        private ScopedRecordQuery $query,
        private SchemaRepository $schemas,
        private ScopeFactory $scopes,
    ) {}

    private function scopeFor(User $user, string $targetEntityId): AccessScope
    {
        $schema = $this->schemas->publishedById($targetEntityId);

        if ($schema === null) {
            // Entity Core fisik (unit organisasi): hanya lewat visibilitas publik/internal.
            return AccessScope::read([], $user->kind === 'internal');
        }

        // Lapis 1 (permission) wajib lolos; visibilitas hanya menggantikan lapis 2 (scope).
        return $this->scopes->canView($user, $schema)
            ? $this->scopes->read($user, $schema)
            : AccessScope::write([]);
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
     * Pencarian target untuk EntitySelector (docs/06 §3): judul/nama cocok dengan `$term`,
     * dibatasi cakupan baca user pada entity target. Tanpa preload ratusan opsi.
     *
     * @return list<array{value: string, label: string}>
     */
    public function search(User $user, string $targetEntityId, string $term, int $limit = self::SEARCH_LIMIT): array
    {
        $scope = $this->scopeFor($user, $targetEntityId);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $rows = $this->schemas->publishedById($targetEntityId) !== null
            ? $this->query->records($targetEntityId, $scope)
                ->where(fn (Builder $q) => $q
                    ->whereRaw("r.search @@ plainto_tsquery('simple', ?)", [$term])
                    ->orWhere('r.title', 'ilike', $like))
                ->orderBy('r.title')->limit($limit)->get(['o.id', 'r.title'])
            : $this->query->objects($targetEntityId, $scope)
                ->join('core_organizations as c', 'c.id', '=', 'o.id')
                ->whereRaw('(c.valid_to IS NULL OR c.valid_to > CURRENT_DATE)')
                ->where('c.name', 'ilike', $like)
                ->orderBy('c.path')->limit($limit)->get(['o.id', 'c.name as title']);

        $options = [];
        foreach ($rows as $row) {
            if (is_string($row->id ?? null) && is_string($row->title ?? null)) {
                $options[] = ['value' => $row->id, 'label' => $row->title];
            }
        }

        return $options;
    }

    /**
     * Judul target relasi sebuah record, dibatasi cakupan baca PEMBACA pada entity target.
     * Target di luar cakupan diberi label netral, tanpa judul aslinya. Satu query per entity
     * target (bukan per tautan).
     *
     * @param  array<string, list<string>>  $links  field_key => target id
     * @return array<string, string> target id => judul
     */
    public function titles(User $user, EntitySchema $schema, array $links): array
    {
        $byTarget = [];
        foreach ($schema->fields as $field) {
            $target = $field->configValue('target_entity_id');
            if ($field->type === 'relationship' && is_string($target) && isset($links[$field->fieldKey])) {
                $byTarget[$target] = [...($byTarget[$target] ?? []), ...$links[$field->fieldKey]];
            }
        }

        $titles = [];
        foreach ($byTarget as $target => $ids) {
            $ids = array_values(array_filter(array_unique($ids), fn (string $id): bool => Str::isUuid($id)));
            if ($ids === []) {
                continue;
            }

            $titles += $this->query->titlesFor($target, $ids, $this->scopeFor($user, $target));
            foreach ($ids as $id) {
                $titles[$id] ??= self::OUT_OF_SCOPE_LABEL;
            }
        }

        return $titles;
    }

    /**
     * URL halaman detail target yang terlihat pembaca (entity terbit saja; Core fisik tidak
     * punya halaman runtime). Dipanggil setelah titles(): id berlabel netral tidak diberi URL.
     *
     * @param  array<string, list<string>>  $links
     * @param  array<string, string>  $titles
     * @return array<string, string>
     */
    public function urls(EntitySchema $schema, array $links, array $titles): array
    {
        $urls = [];
        foreach ($schema->fields as $field) {
            $target = $field->configValue('target_entity_id');
            $targetSchema = $field->type === 'relationship' && is_string($target) && isset($links[$field->fieldKey])
                ? $this->schemas->publishedById($target)
                : null;
            if ($targetSchema === null) {
                continue;
            }
            foreach ($links[$field->fieldKey] as $id) {
                if (($titles[$id] ?? self::OUT_OF_SCOPE_LABEL) !== self::OUT_OF_SCOPE_LABEL) {
                    $urls[$id] = route('runtime.show', ['app' => $targetSchema->applicationCode, 'entity' => $targetSchema->entityCode, 'record' => $id], false);
                }
            }
        }

        return $urls;
    }
}
