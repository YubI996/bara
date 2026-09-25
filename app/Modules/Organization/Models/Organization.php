<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Enums\Sector;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Core.Organization (tabel fisik, docs/04 §5). Id = id objek di registry `objects`.
 *
 * @property string $id
 * @property string|null $parent_id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property Sector $sector
 * @property OrganizationKind $kind
 * @property string $path
 * @property bool $is_internal
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class Organization extends Model
{
    protected $table = 'core_organizations';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id', 'parent_id', 'code', 'name', 'short_name', 'sector', 'kind',
        'path', 'is_internal', 'valid_from', 'valid_to',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sector' => Sector::class,
            'kind' => OrganizationKind::class,
            'is_internal' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Organization, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isActive(): bool
    {
        return $this->valid_to === null || $this->valid_to->isAfter(CarbonImmutable::today());
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function depth(): int
    {
        return substr_count($this->path, '.');
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('valid_to')
            ->orWhere('valid_to', '>', CarbonImmutable::today()->toDateString()));
    }
}
