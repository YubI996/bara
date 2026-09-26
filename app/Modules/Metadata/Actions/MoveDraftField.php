<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\Field;
use Illuminate\Database\ConnectionInterface;

/** Menaikkan/menurunkan urutan field (alternatif keyboard untuk drag, WCAG 2.5.7). */
final readonly class MoveDraftField
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  'up'|'down'  $direction */
    public function execute(Entity $entity, Field $field, string $direction): void
    {
        $this->db->transaction(function () use ($entity, $field, $direction): void {
            $draft = DraftSupport::lockDraft($entity);

            if ($field->entity_version_id !== $draft->id) {
                throw MetadataRuleViolation::on('field', 'Hanya field pada draft yang bisa dipindah.');
            }

            $field = Field::query()->lockForUpdate()->findOrFail($field->id);
            $neighbour = Field::query()
                ->where('entity_version_id', $draft->id)
                ->where('position', $direction === 'up' ? '<' : '>', $field->position)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->lockForUpdate()
                ->first();

            if ($neighbour === null) {
                return;
            }

            [$a, $b] = [$field->position, $neighbour->position];
            $field->forceFill(['position' => $b])->save();
            $neighbour->forceFill(['position' => $a])->save();

            DraftSupport::touch($draft);
        });
    }
}
