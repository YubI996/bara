<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Models\Entity;
use Illuminate\Database\ConnectionInterface;

/** Membuang draft; hanya boleh bila sudah ada versi terbit untuk kembali. */
final readonly class DiscardDraft
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity): void
    {
        $this->db->transaction(function () use ($entity): void {
            $draft = DraftSupport::lockDraft($entity);
            $entity = Entity::query()->findOrFail($entity->id);

            if ($entity->published_version_id === null) {
                throw MetadataRuleViolation::on('draft', 'Draft pertama tidak bisa dibuang karena belum ada versi terbit.');
            }

            $entity->forceFill(['draft_version_id' => null])->save();
            $draft->delete();

            $this->audit->log('metadata.draft_discard', $entity->id, 'metadata.entity', context: ['version' => $draft->version]);
        });
    }
}
