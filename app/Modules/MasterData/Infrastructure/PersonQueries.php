<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Infrastructure;

use App\Modules\MasterData\Support\PiiCipher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;

/**
 * Tampilan orang & pegawai. NIK tidak pernah keluar dari sini selain empat digit terakhir;
 * pencarian NIK lewat HMAC (cocok persis), bukan dekripsi.
 */
final readonly class PersonQueries
{
    public const int LIMIT = 50;

    public function __construct(
        private ConnectionInterface $db,
        private PiiCipher $cipher,
    ) {}

    /** @return list<array<string, mixed>> */
    public function search(string $term): array
    {
        $query = $this->db->table('core_persons as p')
            ->leftJoin('core_employees as e', fn (JoinClause $j) => $j->on('e.person_id', '=', 'p.id')->whereNull('e.valid_to'))
            ->leftJoin('core_organizations as o', 'o.id', '=', 'e.org_id')
            ->select(['p.id', 'p.full_name', 'p.nik_last4', 'e.position', 'o.name as org_name'])
            ->orderBy('p.full_name')->limit(self::LIMIT);

        if (preg_match('/^\d{16}$/', $term) === 1) {
            $query->where('p.nik_hash', $this->cipher->hash($term));
        } elseif ($term !== '') {
            $query->whereRaw("(p.full_name || COALESCE(' · NIK ****' || p.nik_last4, '')) ILIKE ?", ['%'.addcslashes($term, '%_\\').'%']);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[] = [
                'id' => $row->id ?? null,
                'full_name' => $row->full_name ?? '',
                'nik_masked' => PiiCipher::mask(is_string($row->nik_last4 ?? null) ? $row->nik_last4 : null),
                'position' => $row->position ?? null,
                'org_name' => $row->org_name ?? null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $row = $this->db->table('core_persons')->where('id', $id)
            ->first(['id', 'full_name', 'nik_last4', 'birth_date', 'email', 'phone', 'created_at', 'updated_at']);

        if (! is_object($row)) {
            return null;
        }

        $employments = [];
        foreach ($this->db->table('core_employees as e')->join('core_organizations as o', 'o.id', '=', 'e.org_id')
            ->where('e.person_id', $id)->orderByDesc('e.valid_from')
            ->get(['e.id', 'e.nip', 'e.position', 'e.rank', 'e.valid_from', 'e.valid_to', 'o.name as org_name']) as $e) {
            $employments[] = [
                'id' => $e->id ?? null,
                'nip' => $e->nip ?? null,
                'position' => $e->position ?? null,
                'rank' => $e->rank ?? null,
                'org_name' => $e->org_name ?? null,
                'valid_from' => $e->valid_from ?? null,
                'valid_to' => $e->valid_to ?? null,
            ];
        }

        return [
            'id' => $row->id ?? null,
            'full_name' => $row->full_name ?? '',
            'has_nik' => is_string($row->nik_last4 ?? null),
            'nik_masked' => PiiCipher::mask(is_string($row->nik_last4 ?? null) ? $row->nik_last4 : null),
            'birth_date' => $row->birth_date ?? null,
            'email' => $row->email ?? null,
            'phone' => $row->phone ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
            'employments' => $employments,
        ];
    }
}
