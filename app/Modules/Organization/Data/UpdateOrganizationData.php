<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\OrganizationKind;

final readonly class UpdateOrganizationData
{
    public function __construct(
        public ?string $parentId,
        public string $name,
        public ?string $shortName,
        public OrganizationKind $kind,
    ) {}
}
