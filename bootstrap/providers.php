<?php

declare(strict_types=1);

use App\Modules\Access\Providers\AccessServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Data\Providers\DataServiceProvider;
use App\Modules\Eventing\Providers\EventingServiceProvider;
use App\Modules\Metadata\Providers\MetadataServiceProvider;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AuditServiceProvider::class,
    EventingServiceProvider::class,
    AccessServiceProvider::class,
    DataServiceProvider::class,
    MetadataServiceProvider::class,
    OrganizationServiceProvider::class,
];
