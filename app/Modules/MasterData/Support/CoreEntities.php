<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Support;

use App\Modules\Metadata\Contracts\EntityCatalog;

/**
 * Entity Core fisik milik modul master data (docs/04 §5). Organisasi didaftarkan modul
 * Organization saat bootstrap; sisanya di sini.
 */
final readonly class CoreEntities
{
    /** kode => [nama, nama jamak, tabel fisik] */
    public const array ENTITIES = [
        'region' => ['Wilayah', 'Wilayah', 'core_regions'],
        'person' => ['Orang', 'Orang', 'core_persons'],
        'employee' => ['Pegawai', 'Pegawai', 'core_employees'],
        'fiscal_year' => ['Tahun anggaran', 'Tahun anggaran', 'core_fiscal_years'],
    ];

    /** Entity berisi data pribadi: relasi ke sini wajib berklasifikasi personal (ADR 0016). */
    public const array PERSONAL = ['person', 'employee'];

    public function __construct(private EntityCatalog $catalog) {}

    public function sync(): void
    {
        foreach (self::ENTITIES as $code => [$name, $plural, $table]) {
            $this->catalog->ensureCoreEntity($code, $name, $plural, $table);
        }
    }

    public function id(string $code): string
    {
        return $this->catalog->entityId('core', $code);
    }
}
