<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Schema\DraftReport;
use App\Modules\Metadata\Schema\DraftValidator;
use App\Modules\Metadata\Schema\RelationshipTargets;

/** Menyusun laporan kesiapan publikasi draft (dipakai halaman entity dan saat publish). */
final readonly class InspectDraft
{
    public function __construct(
        private DraftValidator $validator,
        private RelationshipTargets $targets,
    ) {}

    public function execute(Entity $entity, EntityVersion $draft): DraftReport
    {
        $published = $entity->published_version_id === null
            ? []
            : EntityVersion::query()->findOrFail($entity->published_version_id)->definitions();

        return $this->validator->check(DraftSupport::context($entity), $draft->definitions(), $published, $this->targets);
    }
}
