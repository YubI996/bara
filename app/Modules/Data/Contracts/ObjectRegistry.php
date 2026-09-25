<?php

declare(strict_types=1);

namespace App\Modules\Data\Contracts;

/**
 * Registry supertype `objects` (ADR 0005). Setiap objek (record maupun baris Core)
 * didaftarkan di sini agar relasi, scope akses, audit, dan event punya satu titik acuan.
 */
interface ObjectRegistry
{
    /** Mendaftarkan objek baru dan mengembalikan id-nya (UUIDv7). */
    public function register(
        string $entityId,
        string $ownerOrgId,
        string $ownerPath,
        Visibility $visibility,
        ?string $id = null,
    ): string;

    /**
     * Memindahkan owner_path semua objek di bawah $oldPath ke $newPath (restrukturisasi organisasi).
     *
     * @return int jumlah objek yang diperbarui
     */
    public function rebaseOwnerPaths(string $oldPath, string $newPath): int;
}
