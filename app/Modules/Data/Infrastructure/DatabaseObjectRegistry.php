<?php

declare(strict_types=1);

namespace App\Modules\Data\Infrastructure;

use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseObjectRegistry implements ObjectRegistry
{
    public function __construct(private ConnectionInterface $db) {}

    public function register(
        string $entityId,
        string $ownerOrgId,
        string $ownerPath,
        Visibility $visibility,
        ?string $id = null,
    ): string {
        $id ??= Str::uuid7()->toString();

        $this->db->table('objects')->insert([
            'id' => $id,
            'entity_id' => $entityId,
            'owner_org_id' => $ownerOrgId,
            'owner_path' => $ownerPath,
            'visibility' => $visibility->value,
        ]);

        return $id;
    }

    public function rebaseOwnerPaths(string $oldPath, string $newPath): int
    {
        return $this->db->update(
            'UPDATE objects SET owner_path = CASE WHEN owner_path = ?::ltree THEN ?::ltree ELSE ?::ltree || subpath(owner_path, nlevel(?::ltree)) END WHERE owner_path <@ ?::ltree',
            [$oldPath, $newPath, $newPath, $oldPath, $oldPath],
        );
    }
}
