<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Contracts;

/**
 * Satu tipe field (docs/06 §1). Implementasi harus deterministik dan tanpa efek samping.
 */
interface FieldType
{
    public function code(): string;

    /** Label Bahasa Indonesia untuk UI admin. */
    public function label(): string;

    /**
     * Aturan validasi Laravel untuk `config` tipe ini (kunci di luar daftar dibuang).
     *
     * @return array<string, list<mixed>>
     */
    public function configRules(): array;

    /**
     * Menerapkan default & membuang kunci tak dikenal. Dipanggil setelah configRules() lolos.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array;

    /**
     * Pemeriksaan lintas-kunci (mis. min ≤ max). Kunci = nama config, nilai = pesan.
     *
     * @param  array<string, mixed>  $config  sudah dinormalisasi
     * @return array<string, string>
     */
    public function configErrors(array $config): array;

    /**
     * Aturan validasi nilai record. Kunci '' untuk nilai field, '.*' untuk elemen array.
     *
     * @return array<string, list<mixed>>
     */
    public function valueRules(FieldDefinition $field): array;

    /** @return array<string, mixed> JSON Schema (2020-12) untuk nilai field */
    public function jsonSchema(FieldDefinition $field): array;

    /** Normalisasi nilai sebelum disimpan ke JSONB (dipakai runtime M2). */
    public function cast(mixed $value): mixed;

    /** Tipe cast PostgreSQL untuk index/filter, null = text / tidak bisa di-index. */
    public function sqlCast(): ?string;

    public function uiComponent(FieldDefinition $field): string;

    public function supportsIndex(): bool;

    public function supportsSearch(): bool;

    public function supportsDefault(): bool;
}
