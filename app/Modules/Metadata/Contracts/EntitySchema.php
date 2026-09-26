<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

/**
 * Skema entity versi terbit, siap dipakai runtime (dibaca dari compiled_schema yang immutable).
 */
final readonly class EntitySchema
{
    /** @param  list<FieldDefinition>  $fields  urut sesuai position */
    public function __construct(
        public string $entityId,
        public string $entityVersionId,
        public int $version,
        public string $applicationId,
        public string $applicationCode,
        public string $applicationName,
        public string $applicationOwnerOrgId,
        public string $entityCode,
        public string $entityName,
        public string $entityNamePlural,
        public string $titleTemplate,
        public string $defaultVisibility,
        public array $fields,
    ) {}

    public function field(string $code): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->code === $code) {
                return $field;
            }
        }

        return null;
    }

    public function fieldByKey(string $fieldKey): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->fieldKey === $fieldKey) {
                return $field;
            }
        }

        return null;
    }

    /** Kode permission untuk aksi pada entity ini, mis. `monev.kegiatan.view`. */
    public function permission(string $action): string
    {
        return "{$this->applicationCode}.{$this->entityCode}.{$action}";
    }
}
