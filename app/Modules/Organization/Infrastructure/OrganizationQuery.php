<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure;

use App\Modules\Access\Contracts\ScopeGrant;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query organisasi yang dibatasi scope akses (setara ScopedRecordQuery untuk Core.Organization).
 */
final class OrganizationQuery
{
    public const int MAX_ROWS = 2000;

    /**
     * @param  list<ScopeGrant>  $grants
     * @return Collection<int, Organization>
     */
    public function within(array $grants, ?string $search = null, bool $activeOnly = false): Collection
    {
        if ($grants === []) {
            return new Collection;
        }

        $query = Organization::query()->where(function (Builder $q) use ($grants): void {
            foreach ($grants as $grant) {
                $grant->includeDescendants
                    ? $q->orWhereRaw('path <@ ?::ltree', [$grant->path])
                    : $q->orWhereRaw('path = ?::ltree', [$grant->path]);
            }
        });

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('short_name', 'ilike', $like)
                ->orWhere('code', 'ilike', $like));
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('path')->limit(self::MAX_ROWS)->get();
    }
}
