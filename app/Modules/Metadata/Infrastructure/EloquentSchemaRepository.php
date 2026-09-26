<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\SchemaRepository;
use App\Modules\Metadata\Models\Entity;
use Illuminate\Support\Str;

/** Memo per request; versi terbit immutable sehingga aman di-cache. */
final class EloquentSchemaRepository implements SchemaRepository
{
    /** @var array<string, EntitySchema|null> */
    private array $memo = [];

    public function published(string $applicationCode, string $entityCode): ?EntitySchema
    {
        $key = $applicationCode.'.'.$entityCode;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $entity = Entity::query()
            ->with(['application', 'publishedVersion'])
            ->where('code', $entityCode)
            ->whereHas('application', fn ($q) => $q->where('code', $applicationCode)->where('status', 'active'))
            ->where('storage_type', 'document')
            ->first();

        return $this->memo[$key] = $entity === null ? null : $this->toSchema($entity);
    }

    public function publishedById(string $entityId): ?EntitySchema
    {
        if (! Str::isUuid($entityId)) {
            return null;
        }

        $entity = Entity::query()->with(['application', 'publishedVersion'])->find($entityId);

        return $entity === null || $entity->storage_type !== 'document' ? null : $this->toSchema($entity);
    }

    public function allPublished(): array
    {
        $schemas = [];
        $entities = Entity::query()
            ->with(['application', 'publishedVersion'])
            ->whereNotNull('published_version_id')
            ->where('storage_type', 'document')
            ->whereHas('application', fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        foreach ($entities as $entity) {
            $schema = $this->toSchema($entity);
            if ($schema !== null) {
                $schemas[] = $schema;
            }
        }

        return $schemas;
    }

    private function toSchema(Entity $entity): ?EntitySchema
    {
        $version = $entity->publishedVersion;

        if ($version === null) {
            return null;
        }

        return new EntitySchema(
            entityId: $entity->id,
            entityVersionId: $version->id,
            version: $version->version,
            applicationId: $entity->application_id,
            applicationCode: $entity->application->code,
            applicationName: $entity->application->name,
            applicationOwnerOrgId: $entity->application->owner_org_id,
            entityCode: $entity->code,
            entityName: $entity->name,
            entityNamePlural: $entity->name_plural,
            titleTemplate: $entity->title_template,
            defaultVisibility: $entity->default_visibility,
            fields: $version->definitions(),
        );
    }
}
