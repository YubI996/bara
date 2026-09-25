<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

use RuntimeException;

final class UnknownEntity extends RuntimeException
{
    public static function for(string $applicationCode, string $entityCode): self
    {
        return new self("Entity [{$applicationCode}.{$entityCode}] belum terdaftar. Jalankan seeder PlatformBootstrap.");
    }
}
