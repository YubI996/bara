<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $application_id
 * @property string $code
 * @property string $name
 * @property string $name_plural
 * @property string|null $description
 * @property string $storage_type
 * @property string|null $physical_table
 * @property bool $is_system
 * @property bool $is_shared
 * @property string $default_visibility
 * @property string $title_template
 * @property string|null $published_version_id
 * @property string|null $draft_version_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Application $application
 * @property-read EntityVersion|null $draftVersion
 * @property-read EntityVersion|null $publishedVersion
 */
final class Entity extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'application_id', 'code', 'name', 'name_plural', 'description', 'storage_type', 'physical_table',
        'is_system', 'is_shared', 'default_visibility', 'title_template', 'published_version_id', 'draft_version_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_shared' => 'boolean'];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<EntityVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EntityVersion::class);
    }

    /** @return BelongsTo<EntityVersion, $this> */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'draft_version_id');
    }

    /** @return BelongsTo<EntityVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'published_version_id');
    }
}
