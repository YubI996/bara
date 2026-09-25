<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

interface EntityCatalog
{
    /** Id entity berdasarkan kode aplikasi + kode entity. Melempar exception bila tidak ada. */
    public function entityId(string $applicationCode, string $entityCode): string;
}
