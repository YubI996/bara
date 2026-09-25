<?php

declare(strict_types=1);

namespace App\Modules\Audit\Contracts;

/**
 * Pencatat audit append-only (ADR 0011). Aksi wajib diaudit: docs/05 §5.
 */
interface AuditLogger
{
    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  diff {field: [lama, baru]}
     * @param  array<string, scalar|null>  $context  data tambahan non-PII
     */
    public function log(
        string $action,
        ?string $objectId = null,
        ?string $objectType = null,
        array $changes = [],
        array $context = [],
        ?string $actorId = null,
    ): void;
}
