<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

/**
 * Event in-process setelah versi entity terbit (di dalam transaksi publikasi).
 * Listener yang melakukan kerja berat wajib memakai job afterCommit.
 */
final readonly class EntityVersionPublished
{
    public function __construct(
        public string $entityId,
        public string $entityVersionId,
        public string $applicationId,
    ) {}
}
