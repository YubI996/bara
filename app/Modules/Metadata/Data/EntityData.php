<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Data;

final readonly class EntityData
{
    public function __construct(
        public string $name,
        public string $namePlural,
        public ?string $description,
        public string $defaultVisibility,
        public bool $isShared,
        public string $titleTemplate,
        public ?string $code = null,
    ) {}
}
