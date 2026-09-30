<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Contracts\ConsumerRegistry;
use Illuminate\Database\ConnectionInterface;

/** Hanya hasil positif yang di-memo, supaya persetujuan/pencabutan langsung berlaku. */
final class DatabaseConsumerRegistry implements ConsumerRegistry
{
    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function allows(string $applicationId, string $entityId): bool
    {
        $key = $applicationId.'|'.$entityId;

        if (isset($this->memo[$key])) {
            return true;
        }

        // Pemilik selalu boleh (memo aman karena kepemilikan entity tidak berubah).
        if ($this->db->table('entities')->where('id', $entityId)->where('application_id', $applicationId)->exists()) {
            return $this->memo[$key] = true;
        }

        return $this->db->table('entity_consumers')
            ->where('entity_id', $entityId)
            ->where('application_id', $applicationId)
            ->where('status', 'approved')
            ->exists();
    }
}
