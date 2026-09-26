<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Shared\Data\DataClassification;

/**
 * Penjaga keluaran field berdasarkan clearance (docs/05 §1.2). Semua data yang meninggalkan
 * server (props Inertia, export, event) wajib lewat sini.
 */
final readonly class FieldGate
{
    public const string HIDDEN = 'hidden';

    public const string MASKED = 'masked';

    public const string VISIBLE = 'visible';

    public function __construct(private DataClassification $clearance) {}

    public function clearance(): DataClassification
    {
        return $this->clearance;
    }

    /** @return 'hidden'|'masked'|'visible' */
    public function access(FieldDefinition $field): string
    {
        if ($this->clearance->rank() >= $field->classification->rank()) {
            return self::VISIBLE;
        }

        // Data pribadi umum bertipe teks boleh tampil tersamar; sisanya disembunyikan.
        if ($field->classification === DataClassification::Personal && in_array($field->type, ['string', 'text'], true)) {
            return self::MASKED;
        }

        return self::HIDDEN;
    }

    public function canWrite(FieldDefinition $field): bool
    {
        return $this->access($field) === self::VISIBLE;
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @return list<FieldDefinition>
     */
    public function readable(array $fields): array
    {
        return array_values(array_filter($fields, fn (FieldDefinition $f): bool => $this->access($f) !== self::HIDDEN));
    }

    /** Nilai yang boleh dikirim ke klien untuk field ini. */
    public function present(FieldDefinition $field, mixed $value): mixed
    {
        return match ($this->access($field)) {
            self::VISIBLE => $value,
            self::MASKED => is_string($value) ? self::mask($value) : null,
            default => null,
        };
    }

    /** 4 karakter awal + 4 akhir, sisanya bintang (docs/05 §1.2). */
    public static function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 4).str_repeat('*', $length - 8).mb_substr($value, -4);
    }
}
