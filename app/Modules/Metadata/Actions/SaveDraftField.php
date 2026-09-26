<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\Field;
use App\Modules\Metadata\Schema\FieldConfigValidator;
use App\Modules\Metadata\Schema\RelationshipTargets;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Menambah atau mengubah field pada draft entity. */
final readonly class SaveDraftField
{
    public function __construct(
        private ConnectionInterface $db,
        private FieldConfigValidator $validator,
        private RelationshipTargets $targets,
        private AuditLogger $audit,
    ) {}

    public function execute(Entity $entity, FieldInput $input, ?Field $field = null): Field
    {
        return $this->db->transaction(function () use ($entity, $input, $field): Field {
            $draft = DraftSupport::lockDraft($entity);

            if ($field !== null && $field->entity_version_id !== $draft->id) {
                throw MetadataRuleViolation::on('code', 'Hanya field pada draft yang bisa diubah.');
            }

            $fieldKey = $field !== null ? $field->field_key : Str::uuid7()->toString();
            $probe = new FieldDefinition(
                $fieldKey, $input->code, $input->label, $input->helpText, $input->type, $input->required,
                $input->unique, $input->indexed, $input->searchable, $input->classification, [], 0,
            );
            $result = $this->validator->validate($probe, $input->config);
            $errors = $result['errors'];

            $duplicate = Field::query()->where('entity_version_id', $draft->id)->where('code', $input->code)
                ->when($field !== null, fn ($q) => $q->whereKeyNot($field?->id))->exists();
            if ($duplicate) {
                $errors['code'] = 'Kode sudah dipakai field lain pada entity ini.';
            }

            if ($input->type === 'relationship' && ! isset($errors['config.target_entity_id'])) {
                $targetId = $result['config']['target_entity_id'] ?? null;
                $allowed = array_map(static fn ($t): string => $t->id, $this->targets->selectableFor($entity->application_id));
                if (! is_string($targetId) || ($targetId !== $entity->id && ! in_array($targetId, $allowed, true))) {
                    $errors['config.target_entity_id'] = 'Pilih entity target yang sudah terbit, dalam aplikasi ini atau dibagikan (shared).';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $attributes = [
                'code' => $input->code,
                'label' => $input->label,
                'help_text' => $input->helpText,
                'type' => $input->type,
                'is_required' => $input->required,
                'is_unique' => $input->unique,
                'is_indexed' => $input->indexed,
                'is_searchable' => $input->searchable,
                'classification' => $input->classification,
                'config' => $result['config'],
            ];

            if ($field === null) {
                $max = Field::query()->where('entity_version_id', $draft->id)->max('position');
                $position = (is_numeric($max) ? (int) $max : 0) + 1;
                $field = Field::query()->create([
                    ...$attributes,
                    'entity_version_id' => $draft->id,
                    'field_key' => $fieldKey,
                    'position' => $position,
                ]);
                $this->audit->log('metadata.field_add', $entity->id, 'metadata.entity', [
                    $field->code => [null, $field->type],
                ], ['version' => $draft->version]);
            } else {
                $field = Field::query()->lockForUpdate()->findOrFail($field->id);
                $field->fill($attributes);
                $changes = [];
                $originalCode = $field->getRawOriginal('code');
                $prefix = is_string($originalCode) ? $originalCode : $field->code;
                foreach ($field->getDirty() as $key => $value) {
                    $changes[$prefix.'.'.$key] = [$field->getOriginal($key), $value];
                }
                $field->save();
                if ($changes !== []) {
                    $this->audit->log('metadata.field_update', $entity->id, 'metadata.entity', $changes, ['version' => $draft->version]);
                }
            }

            DraftSupport::touch($draft);

            return $field;
        });
    }
}
