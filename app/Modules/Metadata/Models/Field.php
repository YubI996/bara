<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Models;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Shared\Data\DataClassification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field pada satu versi entity. Hanya field versi draft yang boleh diubah.
 *
 * @property string $id
 * @property string $entity_version_id
 * @property string $field_key
 * @property string $code
 * @property string $label
 * @property string|null $help_text
 * @property string $type
 * @property bool $is_required
 * @property bool $is_unique
 * @property bool $is_indexed
 * @property bool $is_searchable
 * @property DataClassification $classification
 * @property array<string, mixed> $config
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read EntityVersion $entityVersion
 */
final class Field extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'entity_version_id', 'field_key', 'code', 'label', 'help_text', 'type', 'is_required', 'is_unique',
        'is_indexed', 'is_searchable', 'classification', 'config', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_indexed' => 'boolean',
            'is_searchable' => 'boolean',
            'classification' => DataClassification::class,
            'config' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<EntityVersion, $this> */
    public function entityVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class);
    }

    public function toDefinition(): FieldDefinition
    {
        return new FieldDefinition(
            $this->field_key,
            $this->code,
            $this->label,
            $this->help_text,
            $this->type,
            $this->is_required,
            $this->is_unique,
            $this->is_indexed,
            $this->is_searchable,
            $this->classification,
            $this->config,
            $this->position,
        );
    }
}
