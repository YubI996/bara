<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

final readonly class RelationshipTarget
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $applicationId,
        public string $applicationCode,
        public bool $isShared,
        public bool $isPublished,
    ) {}

    public function label(): string
    {
        return "{$this->name} ({$this->applicationCode}.{$this->code})";
    }
}
