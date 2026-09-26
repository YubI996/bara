<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\Field;
use Illuminate\Database\ConnectionInterface;

final readonly class RemoveDraftField
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity, Field $field): void
    {
        $this->db->transaction(function () use ($entity, $field): void {
            $draft = DraftSupport::lockDraft($entity);

            if ($field->entity_version_id !== $draft->id) {
                throw MetadataRuleViolation::on('field', 'Hanya field pada draft yang bisa dihapus.');
            }

            $field->delete();

            // Rapatkan urutan agar tetap 1..n.
            $this->db->update(
                'UPDATE fields SET position = ranked.rn FROM (SELECT id, row_number() OVER (ORDER BY position) AS rn FROM fields WHERE entity_version_id = ?) ranked WHERE fields.id = ranked.id',
                [$draft->id],
            );

            $this->audit->log('metadata.field_remove', $entity->id, 'metadata.entity', [
                $field->code => [$field->type, null],
            ], ['version' => $draft->version]);

            DraftSupport::touch($draft);
        });
    }
}
