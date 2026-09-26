<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

final readonly class OrganizationSummary
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $path,
        public int $depth,
        public bool $isActive,
    ) {}
}
