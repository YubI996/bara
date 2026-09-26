<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldMigration;

/**
 * Menerjemahkan perubahan berkategori Migration (ChangeClassifier) menjadi langkah migrasi data.
 * Harus selalu sejalan dengan ChangeClassifier::COMPATIBLE_TYPE_CHANGES.
 */
final class MigrationPlanner
{
    /**
     * @param  list<FieldDefinition>  $previous  field versi terbit sebelumnya
     * @param  list<FieldDefinition>  $next  field versi yang diterbitkan
     * @return list<FieldMigration>
     */
    public function plan(array $previous, array $next): array
    {
        if ($previous === []) {
            return [];
        }

        $before = [];
        foreach ($previous as $field) {
            $before[$field->fieldKey] = $field;
        }

        $plan = [];
        foreach ($next as $field) {
            $old = $before[$field->fieldKey] ?? null;

            if ($old === null) {
                if ($field->required && array_key_exists('default', $field->config) && $field->config['default'] !== null) {
                    $plan[] = new FieldMigration($field->fieldKey, 'backfill', $field->type, $field->config['default']);
                }

                continue;
            }

            if ($old->type === $field->type || ! in_array($field->type, ChangeClassifier::COMPATIBLE_TYPE_CHANGES[$old->type] ?? [], true)) {
                continue;
            }

            $step = match (true) {
                $old->type === 'enum' && $field->type === 'multi_enum' => new FieldMigration($field->fieldKey, 'wrap_array', $field->type),
                $old->type === 'enum' && $field->type === 'string' => new FieldMigration($field->fieldKey, 'enum_label', $field->type, labels: $this->labels($old)),
                $old->type === 'string' && $field->type === 'text' => null,
                default => new FieldMigration($field->fieldKey, 'cast', $field->type),
            };

            if ($step !== null) {
                $plan[] = $step;
            }
        }

        return $plan;
    }

    /** @return array<string, string> */
    private function labels(FieldDefinition $field): array
    {
        $labels = [];
        foreach (is_array($field->config['options'] ?? null) ? $field->config['options'] : [] as $option) {
            if (is_array($option) && is_string($option['value'] ?? null) && is_string($option['label'] ?? null)) {
                $labels[$option['value']] = $option['label'];
            }
        }

        return $labels;
    }
}
