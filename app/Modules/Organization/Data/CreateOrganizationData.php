<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\OrganizationKind;

final readonly class CreateOrganizationData
{
    public function __construct(
        public string $parentId,
        public string $code,
        public string $name,
        public ?string $shortName,
        public OrganizationKind $kind,
    ) {}
}
