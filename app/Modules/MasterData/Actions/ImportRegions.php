<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\MasterData\Support\CoreEntities;
use App\Modules\MasterData\Support\RegionCode;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Impor/pemutakhiran kode wilayah Kemendagri (docs/04 §5). Idempoten: kode baru ditambah, nama
 * berubah diperbarui, kode yang hilang dari sumber dinonaktifkan (valid_to), bukan dihapus,
 * karena bisa sudah dirujuk data lain. Wilayah adalah data publik (visibility public).
 */
final readonly class ImportRegions
{
    private const int BATCH = 1000;

    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private OrganizationDirectory $organizations,
        private CoreEntities $core,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    /**
     * @param  array<array-key, string>  $rows  kode => nama (boleh urutan apa pun)
     * @param  bool  $deactivateMissing  false untuk impor sebagian (mis. satu provinsi)
     * @return array{inserted: int, updated: int, deactivated: int, unchanged: int}
     */
    public function execute(array $rows, string $sourceRef, bool $deactivateMissing = true): array
    {
        if (trim($sourceRef) === '') {
            throw new InvalidArgumentException('source_ref wajib diisi (nomor Kepmendagri sumber).');
        }

        foreach ($rows as $code => $name) {
            // Kode "11" menjadi kunci integer di array PHP; selalu kembalikan ke string.
            $code = (string) $code;
            if (! RegionCode::isValid($code) || trim($name) === '') {
                throw new InvalidArgumentException("Baris tidak valid: {$code}");
            }
            $parent = RegionCode::parent($code);
            if ($parent !== null && ! isset($rows[$parent]) && ! $this->db->table('core_regions')->where('code', $parent)->exists()) {
                throw new InvalidArgumentException("Induk {$parent} untuk {$code} tidak ada di sumber maupun database.");
            }
        }

        // Induk harus ada lebih dulu: urutkan dari level teratas.
        uksort($rows, fn (int|string $a, int|string $b): int => [RegionCode::level((string) $a), (string) $a] <=> [RegionCode::level((string) $b), (string) $b]);

        return $this->db->transaction(function () use ($rows, $sourceRef, $deactivateMissing): array {
            $entityId = $this->core->id('region');
            $root = $this->organizations->root();

            /** @var array<string, array{id: string, name: string, valid_to: ?string}> $existing */
            $existing = [];
            foreach ($this->db->table('core_regions')->get(['id', 'code', 'name', 'valid_to']) as $r) {
                if (is_string($r->id ?? null) && is_string($r->code ?? null) && is_string($r->name ?? null)) {
                    $existing[(string) $r->code] = ['id' => $r->id, 'name' => $r->name, 'valid_to' => is_string($r->valid_to ?? null) ? $r->valid_to : null];
                }
            }

            $stats = ['inserted' => 0, 'updated' => 0, 'deactivated' => 0, 'unchanged' => 0];
            $ids = array_map(fn (array $e): string => $e['id'], $existing);
            $newRows = [];

            foreach ($rows as $code => $name) {
                $code = (string) $code;
                $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

                if (isset($existing[$code])) {
                    if ($existing[$code]['name'] !== $name || $existing[$code]['valid_to'] !== null) {
                        $this->db->table('core_regions')->where('id', $existing[$code]['id'])
                            ->update(['name' => $name, 'source_ref' => $sourceRef, 'valid_to' => null]);
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }

                    continue;
                }

                $id = Str::uuid7()->toString();
                $ids[$code] = $id;
                $parent = RegionCode::parent($code);
                $newRows[] = [
                    'id' => $id,
                    'code' => $code,
                    'name' => $name,
                    'level' => RegionCode::level($code),
                    'parent_id' => $parent === null ? null : $ids[$parent],
                    'source_ref' => $sourceRef,
                ];
            }

            foreach (array_chunk($newRows, self::BATCH) as $chunk) {
                $this->objects->registerMany($entityId, $root->id, $root->path, Visibility::Public, array_column($chunk, 'id'));
                $this->db->table('core_regions')->insert($chunk);
                $stats['inserted'] += count($chunk);
            }

            if ($deactivateMissing) {
                $missing = array_map('strval', array_diff(array_keys($existing), array_keys($rows)));
                foreach (array_chunk(array_values($missing), self::BATCH) as $chunk) {
                    $stats['deactivated'] += $this->db->table('core_regions')->whereIn('code', $chunk)->whereNull('valid_to')
                        ->update(['valid_to' => now()->toDateString()]);
                }
            }

            // Tautkan Pemda (unit akar) ke kode wilayahnya bila dikonfigurasi.
            $regionCode = config('bara.pemda.region_code');
            if (is_string($regionCode) && isset($ids[$regionCode])) {
                $this->organizations->linkRegion($root->id, $ids[$regionCode]);
            }

            $this->audit->log('masterdata.regions_imported', null, 'core.region', context: [...$stats, 'source_ref' => $sourceRef]);
            $this->events->record('masterdata.regions_imported', 'core.region', $entityId, [...$stats, 'source_ref' => $sourceRef]);

            return $stats;
        });
    }
}
