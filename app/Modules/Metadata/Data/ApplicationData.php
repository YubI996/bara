<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Data;

final readonly class ApplicationData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $ownerOrgId,
        public string $status,
        public ?string $code = null,
    ) {}
}
