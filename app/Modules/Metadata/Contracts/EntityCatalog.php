<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

interface EntityCatalog
{
    /** Id entity berdasarkan kode aplikasi + kode entity. Melempar exception bila tidak ada. */
    public function entityId(string $applicationCode, string $entityCode): string;

    /**
     * Daftarkan entity Core fisik (tabel master) pada aplikasi `core` bila belum ada.
     * Idempoten; dipanggil saat bootstrap dan `bara:sync-core`.
     *
     * @return string id entity
     */
    public function ensureCoreEntity(string $code, string $name, string $namePlural, string $physicalTable): string;
}
