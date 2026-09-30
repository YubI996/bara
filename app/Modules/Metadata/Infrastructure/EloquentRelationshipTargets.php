<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Infrastructure;

use App\Modules\Metadata\Contracts\ConsumerRegistry;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Schema\RelationshipTarget;
use App\Modules\Metadata\Schema\RelationshipTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class EloquentRelationshipTargets implements RelationshipTargets
{
    public function __construct(private ConsumerRegistry $consumers) {}

    public function find(string $entityId): ?RelationshipTarget
    {
        $entity = Str::isUuid($entityId) ? Entity::query()->with('application')->find($entityId) : null;

        return $entity === null ? null : $this->toTarget($entity);
    }

    public function selectableFor(string $applicationId): array
    {
        return array_values(Entity::query()
            ->with('application')
            ->where(fn (Builder $q) => $q->where('application_id', $applicationId)->orWhere('is_shared', true))
            ->where(fn (Builder $q) => $q->whereNotNull('published_version_id')->orWhere('storage_type', 'physical'))
            ->orderBy('name')
            ->get()
            // Lintas aplikasi hanya setelah consumer disetujui Walidata (ADR 0016).
            ->filter(fn (Entity $entity): bool => $this->consumers->allows($applicationId, $entity->id))
            ->map(fn (Entity $entity): RelationshipTarget => $this->toTarget($entity))
            ->all());
    }

    private function toTarget(Entity $entity): RelationshipTarget
    {
        return new RelationshipTarget(
            $entity->id,
            $entity->code,
            $entity->name,
            $entity->application_id,
            $entity->application->code,
            $entity->is_shared,
            // Entity Core fisik (mis. organisasi) selalu siap dipakai sebagai target.
            $entity->published_version_id !== null || $entity->storage_type === 'physical',
        );
    }
}
