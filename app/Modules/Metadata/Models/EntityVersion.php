<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Models;

use App\Modules\Metadata\Contracts\FieldDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $entity_id
 * @property int $version
 * @property string $status
 * @property array<string, mixed>|null $compiled_schema
 * @property string|null $change_summary
 * @property CarbonImmutable|null $published_at
 * @property string|null $published_by
 * @property string|null $privacy_reviewed_by
 * @property CarbonImmutable|null $privacy_reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Entity $entity
 */
final class EntityVersion extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'entity_id', 'version', 'status', 'compiled_schema', 'change_summary',
        'published_at', 'published_by', 'privacy_reviewed_by', 'privacy_reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'compiled_schema' => 'array',
            'published_at' => 'immutable_datetime',
            'privacy_reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return HasMany<Field, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('position');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Field versi ini. Untuk versi terbit dibaca dari compiled_schema (sumber kebenaran immutable).
     *
     * @return list<FieldDefinition>
     */
    public function definitions(): array
    {
        if (! $this->isDraft() && is_array($this->compiled_schema['fields'] ?? null)) {
            $definitions = [];
            foreach ($this->compiled_schema['fields'] as $field) {
                if (is_array($field)) {
                    $definitions[] = FieldDefinition::fromArray($field);
                }
            }

            return $definitions;
        }

        return array_values($this->fields()->get()->map(fn (Field $field): FieldDefinition => $field->toDefinition())->all());
    }
}
