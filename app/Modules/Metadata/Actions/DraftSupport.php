<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Schema\EntityContext;

/**
 * Utilitas bersama Action metadata: mengunci draft dan membangun konteks entity.
 */
final class DraftSupport
{
    /** Mengunci entity + draft-nya (FOR UPDATE). Melempar error form bila tidak ada draft. */
    public static function lockDraft(Entity $entity): EntityVersion
    {
        $locked = Entity::query()->lockForUpdate()->findOrFail($entity->id);
        $draft = $locked->draft_version_id === null
            ? null
            : EntityVersion::query()->lockForUpdate()->find($locked->draft_version_id);

        if ($draft === null || ! $draft->isDraft()) {
            throw MetadataRuleViolation::on('draft', 'Entity ini tidak punya draft. Buat draft baru untuk mengubah field.');
        }

        return $draft;
    }

    /** Setiap perubahan draft membatalkan persetujuan PDP yang sudah ada. */
    public static function touch(EntityVersion $draft): void
    {
        $draft->forceFill([
            'privacy_reviewed_by' => null,
            'privacy_reviewed_at' => null,
            'updated_at' => now(),
        ])->save();
    }

    public static function context(Entity $entity): EntityContext
    {
        return new EntityContext(
            $entity->id,
            $entity->code,
            $entity->name,
            $entity->application_id,
            $entity->application->code,
            $entity->title_template,
            $entity->is_shared,
            $entity->default_visibility,
        );
    }
}
