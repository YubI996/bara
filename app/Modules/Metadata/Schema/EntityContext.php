<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

final readonly class EntityContext
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $applicationId,
        public string $applicationCode,
        public string $titleTemplate,
        public bool $isShared,
        public string $defaultVisibility,
    ) {}
}
