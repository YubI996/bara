<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry relasi aktif (dipakai record_links pada M3). Disinkronkan saat publikasi.
 *
 * @property string $id
 * @property string $field_key
 * @property string $source_entity_id
 * @property string $target_entity_id
 * @property string $code
 * @property string $cardinality
 * @property string|null $inverse_code
 * @property string $on_target_delete
 * @property bool $is_active
 */
final class Relationship extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'field_key', 'source_entity_id', 'target_entity_id', 'code', 'cardinality',
        'inverse_code', 'on_target_delete', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
