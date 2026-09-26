<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\FieldDefinition;

/**
 * Menyusun judul record dari template ({kode}). Field data pribadi tidak pernah dipakai
 * dalam judul karena judul tampil di daftar, relasi, dan audit.
 */
final class TitleRenderer
{
    public const int MAX_LENGTH = 250;

    /** @param  array<string, mixed>  $values  berkunci field_key */
    public function render(EntitySchema $schema, array $values): string
    {
        $template = trim($schema->titleTemplate);

        if ($template === '') {
            foreach ($schema->fields as $field) {
                if (in_array($field->type, ['string', 'text'], true) && ! $field->classification->isPersonal()) {
                    $template = '{'.$field->code.'}';
                    break;
                }
            }
        }

        $title = preg_replace_callback('/\{([a-z][a-z0-9_]{1,62})\}/', function (array $m) use ($schema, $values): string {
            $field = $schema->field($m[1]);

            return $field === null || $field->classification->isPersonal()
                ? ''
                : $this->display($field, $values[$field->fieldKey] ?? null);
        }, $template) ?? '';

        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');

        return $title === '' ? "{$schema->entityName} tanpa judul" : mb_substr($title, 0, self::MAX_LENGTH);
    }

    private function display(FieldDefinition $field, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($field->type === 'enum' && is_array($field->config['options'] ?? null)) {
            foreach ($field->config['options'] as $option) {
                if (is_array($option) && ($option['value'] ?? null) === $value && is_string($option['label'] ?? null)) {
                    return $option['label'];
                }
            }
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return is_scalar($value) ? strip_tags((string) $value) : '';
    }
}
