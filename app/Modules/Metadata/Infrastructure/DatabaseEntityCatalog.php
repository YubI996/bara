<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Contracts\EntityCatalog;
use App\Modules\Metadata\Contracts\UnknownEntity;
use Illuminate\Database\ConnectionInterface;

final class DatabaseEntityCatalog implements EntityCatalog
{
    /** @var array<string, string> */
    private array $memo = [];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function entityId(string $applicationCode, string $entityCode): string
    {
        $key = $applicationCode.'.'.$entityCode;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $id = $this->db->table('entities as e')
            ->join('applications as a', 'a.id', '=', 'e.application_id')
            ->where('a.code', $applicationCode)
            ->where('e.code', $entityCode)
            ->value('e.id');

        if (! is_string($id)) {
            throw UnknownEntity::for($applicationCode, $entityCode);
        }

        return $this->memo[$key] = $id;
    }
}
