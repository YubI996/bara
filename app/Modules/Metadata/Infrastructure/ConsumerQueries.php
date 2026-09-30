<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Models\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Data tampilan pendaftaran consumer (halaman aplikasi & halaman Walidata). */
final readonly class ConsumerQueries
{
    public const array STATUS_LABELS = [
        'pending' => 'Menunggu keputusan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'revoked' => 'Dicabut',
    ];

    public function __construct(private ConnectionInterface $db) {}

    /** @return list<array<string, mixed>> */
    public function forApplication(Application $application): array
    {
        return $this->present($this->base()->where('c.application_id', $application->id)->orderBy('e.name')->get());
    }

    /**
     * Entity bersama yang masih bisa diajukan aplikasi ini.
     *
     * @return list<array{value: string, label: string}>
     */
    public function consumable(Application $application): array
    {
        $rows = $this->db->table('entities as e')
            ->join('applications as a', 'a.id', '=', 'e.application_id')
            ->where('e.is_shared', true)
            ->where('e.application_id', '<>', $application->id)
            ->where(fn (Builder $q) => $q->whereNotNull('e.published_version_id')->orWhere('e.storage_type', 'physical'))
            ->whereNotExists(fn (Builder $q) => $q->from('entity_consumers as c')
                ->whereColumn('c.entity_id', 'e.id')
                ->where('c.application_id', $application->id)
                ->whereIn('c.status', ['pending', 'approved']))
            ->orderBy('a.name')->orderBy('e.name')
            ->get(['e.id', 'e.name', 'a.name as app_name']);

        $options = [];
        foreach ($rows as $row) {
            if (is_string($row->id ?? null) && is_string($row->name ?? null) && is_string($row->app_name ?? null)) {
                $options[] = ['value' => $row->id, 'label' => "{$row->name} — {$row->app_name}"];
            }
        }

        return $options;
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return $this->present($this->base()->where('c.status', 'pending')->orderBy('c.requested_at')->get());
    }

    /** @return list<array<string, mixed>> */
    public function decided(int $limit = 50): array
    {
        return $this->present($this->base()->where('c.status', '<>', 'pending')->orderByDesc('c.decided_at')->limit($limit)->get());
    }

    private function base(): Builder
    {
        return $this->db->table('entity_consumers as c')
            ->join('entities as e', 'e.id', '=', 'c.entity_id')
            ->join('applications as owner', 'owner.id', '=', 'e.application_id')
            ->join('applications as a', 'a.id', '=', 'c.application_id')
            ->leftJoin('users as rq', 'rq.id', '=', 'c.requested_by')
            ->leftJoin('users as dc', 'dc.id', '=', 'c.decided_by')
            ->select([
                'c.entity_id', 'c.application_id', 'c.status', 'c.reason', 'c.requested_at', 'c.decided_at', 'c.decision_note',
                'e.name as entity_name', 'owner.name as owner_name', 'a.name as app_name', 'a.code as app_code',
                'rq.name as requested_by', 'dc.name as decided_by',
            ]);
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function present(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $status = is_string($row->status ?? null) ? $row->status : 'pending';
            $out[] = [
                'entity_id' => $row->entity_id ?? null,
                'application_id' => $row->application_id ?? null,
                'entity' => (is_string($row->entity_name ?? null) ? $row->entity_name : '').' — '.(is_string($row->owner_name ?? null) ? $row->owner_name : ''),
                'application' => $row->app_name ?? null,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? $status,
                'reason' => $row->reason ?? null,
                'requested_by' => $row->requested_by ?? 'Migrasi sistem',
                'requested_at' => $row->requested_at ?? null,
                'decided_by' => $row->decided_by ?? null,
                'decided_at' => $row->decided_at ?? null,
                'decision_note' => $row->decision_note ?? null,
            ];
        }

        return $out;
    }
}
