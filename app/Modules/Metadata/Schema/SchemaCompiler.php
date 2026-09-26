<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Modules\Metadata\FieldTypes\RelationshipType;

/**
 * Menghasilkan compiled_schema (docs/06 §2 langkah 4). Hasil deterministik: input sama → JSON sama.
 */
final readonly class SchemaCompiler
{
    public const int FORMAT = 1;

    public function __construct(private FieldTypeRegistry $types) {}

    /**
     * @param  list<FieldDefinition>  $fields  urut sesuai position
     * @return array<string, mixed>
     */
    public function compile(EntityContext $entity, int $version, array $fields): array
    {
        $properties = [];
        $required = [];
        $ui = [];
        $indexes = [];
        $relationships = [];

        foreach ($fields as $field) {
            $type = $this->types->get($field->type);
            $properties[$field->code] = $type->jsonSchema($field);

            if ($field->required) {
                $required[] = $field->code;
            }

            $uiField = [
                'field_key' => $field->fieldKey,
                'code' => $field->code,
                'label' => $field->label,
                'help_text' => $field->helpText,
                'component' => $type->uiComponent($field),
                'required' => $field->required,
                'classification' => $field->classification->value,
            ];
            if (isset($field->config['options'])) {
                $uiField['options'] = $field->config['options'];
            }
            $ui[] = $uiField;

            if ($field->indexed && ($cast = $type->sqlCast()) !== null) {
                $indexes[] = ['field_key' => $field->fieldKey, 'code' => $field->code, 'sql_cast' => $cast, 'unique' => $field->unique];
            }

            if ($type instanceof RelationshipType) {
                $relationships[] = [
                    'field_key' => $field->fieldKey,
                    'code' => $field->code,
                    'target_entity_id' => $field->configValue('target_entity_id'),
                    'cardinality' => $field->configValue('cardinality', 'many_to_one'),
                    'on_target_delete' => $field->configValue('on_target_delete', 'restrict'),
                    'inverse_code' => $field->configValue('inverse_code'),
                ];
            }
        }

        return [
            'format' => self::FORMAT,
            'entity' => [
                'id' => $entity->id,
                'code' => $entity->code,
                'application_code' => $entity->applicationCode,
                'title_template' => $entity->titleTemplate,
            ],
            'version' => $version,
            'fields' => array_map(static fn (FieldDefinition $f): array => $f->toArray(), $fields),
            'json_schema' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => "urn:bara:schema:{$entity->applicationCode}.{$entity->code}:v{$version}",
                'title' => $entity->name,
                'type' => 'object',
                'properties' => $properties === [] ? new \stdClass : $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
            'ui' => $ui,
            'indexes' => $indexes,
            'relationships' => $relationships,
        ];
    }
}
