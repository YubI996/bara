<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Infrastructure;

use App\Modules\MasterData\Support\RegionCode;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/** Query tampilan master data (hanya baca). */
final readonly class MasterDataQueries
{
    public const array FISCAL_STATUS = ['planning' => 'Perencanaan', 'running' => 'Berjalan', 'closed' => 'Ditutup'];

    public function __construct(private ConnectionInterface $db) {}

    /** @return array{regions: int, persons: int, employees: int, fiscal_years: int, source_ref: ?string} */
    public function counts(): array
    {
        $source = $this->db->table('core_regions')->whereNull('valid_to')->orderByDesc('code')->value('source_ref');

        return [
            'regions' => $this->db->table('core_regions')->whereNull('valid_to')->count(),
            'persons' => $this->db->table('core_persons')->count(),
            'employees' => $this->db->table('core_employees')->whereNull('valid_to')->count(),
            'fiscal_years' => $this->db->table('core_fiscal_years')->count(),
            'source_ref' => is_string($source) ? $source : null,
        ];
    }

    /**
     * Wilayah: turunan satu induk (null = provinsi), atau hasil cari nama/kode di semua level.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, last_page: int, parent: ?array<string, mixed>, trail: list<array<string, mixed>>}
     */
    public function regions(?string $parentCode, string $term, int $page): array
    {
        $query = $this->db->table('core_regions')->whereNull('valid_to');

        if ($term !== '') {
            // Ekspresi sama dengan index core_regions_label_trgm.
            $query->whereRaw("(name || ' (' || code || ')') ILIKE ?", ['%'.addcslashes($term, '%_\\').'%']);
        } else {
            $parentId = $parentCode === null ? null : $this->db->table('core_regions')->where('code', $parentCode)->value('id');
            $parentCode !== null && ! is_string($parentId)
                ? $query->whereRaw('false')
                : $query->where('parent_id', $parentId);
        }

        /** @var LengthAwarePaginator<int, object> $paginator */
        $paginator = $query->orderBy('code')->paginate(50, ['code', 'name', 'level', 'source_ref'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $row) {
            $level = is_numeric($row->level ?? null) ? (int) $row->level : 1;
            $items[] = [
                'code' => $row->code ?? '',
                'name' => $row->name ?? '',
                'level' => $level,
                'level_label' => RegionCode::LEVEL_LABELS[$level] ?? '',
                'has_children' => $level < 4,
            ];
        }

        $trail = [];
        for ($code = $term === '' ? $parentCode : null; $code !== null; $code = RegionCode::parent($code)) {
            $name = $this->db->table('core_regions')->where('code', $code)->value('name');
            array_unshift($trail, ['code' => $code, 'name' => is_string($name) ? $name : $code]);
        }

        return [
            'items' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'parent' => $trail === [] ? null : $trail[array_key_last($trail)],
            'trail' => $trail,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function fiscalYears(): array
    {
        $out = [];
        foreach ($this->db->table('core_fiscal_years')->orderByDesc('year')->get(['id', 'year', 'starts_on', 'ends_on', 'status']) as $row) {
            $status = is_string($row->status ?? null) ? $row->status : 'planning';
            $out[] = [
                'id' => $row->id ?? null,
                'year' => $row->year ?? null,
                'starts_on' => $row->starts_on ?? null,
                'ends_on' => $row->ends_on ?? null,
                'status' => $status,
                'status_label' => self::FISCAL_STATUS[$status] ?? $status,
            ];
        }

        return $out;
    }
}
