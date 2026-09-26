<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Models\Entity;
use Illuminate\Database\ConnectionInterface;

/**
 * Persetujuan Pejabat PDP atas field data pribadi di draft (UU 27/2022; docs/05 §3).
 * Persetujuan batal otomatis bila draft diubah setelahnya.
 */
final readonly class ReviewDraftPrivacy
{
    public function __construct(
        private ConnectionInterface $db,
        private InspectDraft $inspect,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity, User $reviewer): void
    {
        $this->db->transaction(function () use ($entity, $reviewer): void {
            $draft = DraftSupport::lockDraft($entity);
            $entity = Entity::query()->with('application')->findOrFail($entity->id);

            if (! $this->inspect->execute($entity, $draft)->requiresPrivacyReview) {
                throw MetadataRuleViolation::on('privacy', 'Draft ini tidak memuat perubahan data pribadi yang perlu ditinjau.');
            }

            $draft->forceFill([
                'privacy_reviewed_by' => $reviewer->id,
                'privacy_reviewed_at' => now(),
            ])->save();

            $this->audit->log('metadata.privacy_review', $entity->id, 'metadata.entity', context: ['version' => $draft->version]);
        });
    }
}
