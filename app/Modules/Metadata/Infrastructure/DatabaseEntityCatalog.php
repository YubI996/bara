<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Contracts\EntityCatalog;
use App\Modules\Metadata\Contracts\UnknownEntity;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

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

    public function ensureCoreEntity(string $code, string $name, string $namePlural, string $physicalTable): string
    {
        $appId = $this->db->table('applications')->where('code', 'core')->where('is_system', true)->value('id');

        if (! is_string($appId)) {
            throw UnknownEntity::for('core', $code);
        }

        $existing = $this->db->table('entities')->where('application_id', $appId)->where('code', $code)->value('id');

        if (is_string($existing)) {
            return $existing;
        }

        $id = Str::uuid7()->toString();
        $this->db->table('entities')->insert([
            'id' => $id,
            'application_id' => $appId,
            'code' => $code,
            'name' => $name,
            'name_plural' => $namePlural,
            'storage_type' => 'physical',
            'physical_table' => $physicalTable,
            'is_system' => true,
            'is_shared' => true,
            'default_visibility' => 'internal',
        ]);

        return $this->memo['core.'.$code] = $id;
    }
}
