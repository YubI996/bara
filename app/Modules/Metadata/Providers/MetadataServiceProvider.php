<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Providers;

use App\Modules\Metadata\Contracts\EntityCatalog;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Modules\Metadata\FieldTypes\Registry;
use App\Modules\Metadata\Infrastructure\DatabaseEntityCatalog;
use App\Modules\Metadata\Infrastructure\EloquentRelationshipTargets;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Policies\ApplicationPolicy;
use App\Modules\Metadata\Policies\EntityPolicy;
use App\Modules\Metadata\Schema\RelationshipTargets;
use Illuminate\Contracts\Foundation\Application as Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EntityCatalog::class, fn (Container $app): EntityCatalog => new DatabaseEntityCatalog(
            $app->make('db')->connection(),
        ));
        $this->app->singleton(FieldTypeRegistry::class, Registry::class);
        $this->app->scoped(RelationshipTargets::class, EloquentRelationshipTargets::class);
    }

    public function boot(): void
    {
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Entity::class, EntityPolicy::class);
    }
}
