<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Presenters;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldType;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Modules\Metadata\FieldTypes\FileType;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Schema\RelationshipTarget;
use App\Modules\Metadata\Schema\RelationshipTargets;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Shared\Data\DataClassification;

/** Bentuk data metadata untuk props Inertia (hanya field yang dibutuhkan UI). */
final readonly class MetadataPresenter
{
    public const array VISIBILITY_LABELS = [
        'private' => 'Privat (hanya unit pemilik)',
        'internal' => 'Internal (semua ASN Pemda)',
        'partner' => 'Mitra (termasuk mitra Pentahelix terverifikasi)',
        'public' => 'Publik',
    ];

    public function __construct(
        private FieldTypeRegistry $types,
        private OrganizationDirectory $organizations,
        private RelationshipTargets $targets,
    ) {}

    /** @return array<string, mixed> */
    public function application(Application $application): array
    {
        return [
            'id' => $application->id,
            'code' => $application->code,
            'name' => $application->name,
            'description' => $application->description,
            'owner_org_id' => $application->owner_org_id,
            'owner_name' => $this->organizations->find($application->owner_org_id)?->name,
            'status' => $application->status,
            'status_label' => $application->statusLabel(),
            'is_system' => $application->is_system,
        ];
    }

    /** @return array<string, mixed> */
    public function entity(Entity $entity): array
    {
        return [
            'id' => $entity->id,
            'code' => $entity->code,
            'name' => $entity->name,
            'name_plural' => $entity->name_plural,
            'description' => $entity->description,
            'default_visibility' => $entity->default_visibility,
            'default_visibility_label' => self::VISIBILITY_LABELS[$entity->default_visibility] ?? $entity->default_visibility,
            'is_shared' => $entity->is_shared,
            'is_system' => $entity->is_system,
            'storage_type' => $entity->storage_type,
            'title_template' => $entity->title_template,
            'has_draft' => $entity->draft_version_id !== null,
            'has_published' => $entity->published_version_id !== null,
        ];
    }

    /** @return array<string, mixed> */
    public function field(FieldDefinition $field, ?string $id = null): array
    {
        $type = $this->types->has($field->type) ? $this->types->get($field->type) : null;

        return [
            'id' => $id,
            'field_key' => $field->fieldKey,
            'code' => $field->code,
            'label' => $field->label,
            'help_text' => $field->helpText,
            'type' => $field->type,
            'type_label' => $type?->label() ?? $field->type,
            'is_required' => $field->required,
            'is_unique' => $field->unique,
            'is_indexed' => $field->indexed,
            'is_searchable' => $field->searchable,
            'classification' => $field->classification->value,
            'classification_label' => $field->classification->label(),
            'is_personal' => $field->classification->isPersonal(),
            'config' => $field->config,
            'position' => $field->position,
            'summary' => $this->configSummary($field),
        ];
    }

    /** @return array<string, mixed> data pilihan untuk form field */
    public function fieldFormOptions(Entity $entity): array
    {
        return [
            'types' => array_map(static fn (FieldType $t): array => [
                'value' => $t->code(),
                'label' => $t->label(),
                'supports_index' => $t->supportsIndex(),
                'supports_search' => $t->supportsSearch(),
                'supports_default' => $t->supportsDefault(),
            ], $this->types->all()),
            'classifications' => DataClassification::options(),
            'targets' => [
                ['value' => $entity->id, 'label' => "{$entity->name} (entity ini sendiri)"],
                ...array_values(array_map(
                    static fn (RelationshipTarget $t): array => ['value' => $t->id, 'label' => $t->label()],
                    array_filter(
                        $this->targets->selectableFor($entity->application_id),
                        static fn (RelationshipTarget $t): bool => $t->id !== $entity->id,
                    ),
                )),
            ],
            'file_extensions' => FileType::ALLOWED_EXTENSIONS,
        ];
    }

    private function configSummary(FieldDefinition $field): string
    {
        $c = $field->config;
        $parts = [];

        if (isset($c['max_length']) && is_int($c['max_length'])) {
            $parts[] = "maks {$c['max_length']} karakter";
        }
        if (isset($c['min']) && is_scalar($c['min']) && $field->type !== 'percentage' && $field->type !== 'money') {
            $parts[] = 'min '.$c['min'];
        }
        if (isset($c['max']) && is_scalar($c['max']) && $field->type !== 'percentage') {
            $parts[] = 'maks '.$c['max'];
        }
        if (isset($c['options']) && is_array($c['options'])) {
            $parts[] = count($c['options']).' opsi';
        }
        if (is_string($c['target_entity_id'] ?? null)) {
            $target = $this->targets->find($c['target_entity_id']);
            $parts[] = '→ '.($target->name ?? 'entity tidak ditemukan')
                .(($c['cardinality'] ?? '') === 'many_to_many' ? ' (banyak)' : ' (satu)');
        }
        if (isset($c['mimes']) && is_array($c['mimes'])) {
            $parts[] = implode(', ', array_filter($c['mimes'], 'is_string'));
        }
        if (array_key_exists('default', $c) && is_scalar($c['default'])) {
            $default = $c['default'];
            $parts[] = 'default '.(is_bool($default) ? ($default ? 'ya' : 'tidak') : (string) $default);
        }

        return implode(' · ', $parts);
    }
}
