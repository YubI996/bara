<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * SATU-SATUNYA pintu query ke `records`/`objects` untuk data bisnis (CLAUDE.md, ADR 0007).
 * Filter scope selalu ditambahkan; tidak ada jalan pintas kecuali AccessScope::system().
 */
final readonly class ScopedRecordQuery
{
    public function __construct(private ConnectionInterface $db) {}

    /** records r JOIN objects o, terbatas pada entity dan cakupan akses. */
    public function records(string $entityId, AccessScope $scope): Builder
    {
        $query = $this->db->table('records as r')
            ->join('objects as o', 'o.id', '=', 'r.id')
            ->where('r.entity_id', $entityId)
            ->whereNull('r.deleted_at');

        return $this->applyScope($query, $scope);
    }

    /** Objek apa pun (termasuk Core fisik) dari satu entity, terbatas cakupan akses. */
    public function objects(string $entityId, AccessScope $scope): Builder
    {
        $query = $this->db->table('objects as o')
            ->where('o.entity_id', $entityId)
            ->whereNull('o.deleted_at');

        return $this->applyScope($query, $scope);
    }

    /**
     * Judul objek untuk tampilan relasi. Hanya dipanggil untuk id yang SUDAH lolos scope
     * (tautan yang tersimpan divalidasi saat ditulis), dan hanya mengembalikan judul.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function titlesFor(array $ids): array
    {
        $rows = $this->db->table('objects as o')
            ->leftJoin('records as r', 'r.id', '=', 'o.id')
            ->leftJoin('core_organizations as c', 'c.id', '=', 'o.id')
            ->whereIn('o.id', $ids)
            ->whereNull('o.deleted_at')
            ->get(['o.id', 'r.title', 'c.name']);

        $titles = [];
        foreach ($rows as $row) {
            $title = is_string($row->title ?? null) ? $row->title : ($row->name ?? null);
            if (is_string($row->id ?? null) && is_string($title)) {
                $titles[$row->id] = $title;
            }
        }

        return $titles;
    }

    private function applyScope(Builder $query, AccessScope $scope): Builder
    {
        if ($scope->system) {
            return $query;
        }

        if ($scope->isEmpty()) {
            return $query->whereRaw('false');
        }

        return $query->where(function (Builder $q) use ($scope): void {
            foreach ($scope->grants as $grant) {
                $grant->includeDescendants
                    ? $q->orWhereRaw('o.owner_path <@ ?::ltree', [$grant->path])
                    : $q->orWhereRaw('o.owner_path = ?::ltree', [$grant->path]);
            }

            if ($scope->includeVisibility) {
                $visible = $scope->isInternal ? ['public', 'internal', 'partner'] : ['public'];
                $q->orWhereIn('o.visibility', $visible);
            }

            // Tanpa satu pun syarat, OR-group kosong harus tetap menolak semua baris.
            $q->orWhereRaw('false');
        });
    }
}
