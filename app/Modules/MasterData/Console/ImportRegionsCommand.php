<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Console;

use App\Modules\MasterData\Actions\ImportRegions;
use App\Modules\MasterData\Support\RegionCode;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Impor kode wilayah dari berkas: dump SQL `('kode','nama')` (mis. cahyadsn/wilayah, MIT) atau
 * CSV `kode,nama`. Contoh:
 *   php artisan bara:import-regions storage/app/wilayah.sql --source-ref="Kepmendagri 300.2.2-2430 Tahun 2025"
 *   php artisan bara:import-regions wilayah.sql --prefix=64 --source-ref="..."   # satu provinsi
 */
final class ImportRegionsCommand extends Command
{
    protected $signature = 'bara:import-regions
        {file : Berkas .sql (INSERT ... VALUES) atau .csv (kode,nama)}
        {--source-ref= : Nomor Kepmendagri sumber, wajib}
        {--prefix= : Hanya kode dengan awalan ini (beserta induknya), mis. 64 atau 64.72}
        {--dry-run : Hitung saja tanpa menyimpan}';

    protected $description = 'Impor/pemutakhiran kode wilayah administrasi Kemendagri ke master data Core';

    public function handle(ImportRegions $import): int
    {
        $file = (string) $this->argument('file');
        $sourceRef = trim((string) $this->option('source-ref'));
        $prefix = $this->option('prefix');

        if ($sourceRef === '') {
            $this->error('--source-ref wajib diisi, mis. "Kepmendagri 300.2.2-2430 Tahun 2025".');

            return self::FAILURE;
        }
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Berkas {$file} tidak ditemukan.");

            return self::FAILURE;
        }

        $rows = self::parse($file);
        if (is_string($prefix) && $prefix !== '') {
            $rows = array_filter($rows, fn (string $name, int|string $code): bool => str_starts_with((string) $code, $prefix) || str_starts_with($prefix, (string) $code), ARRAY_FILTER_USE_BOTH);
        }

        $counts = array_count_values(array_map(RegionCode::level(...), array_map('strval', array_keys($rows))));
        ksort($counts);
        foreach ($counts as $level => $n) {
            $this->line(sprintf('%-16s %s', RegionCode::LEVEL_LABELS[$level] ?? "Level {$level}", number_format($n, 0, ',', '.')));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: tidak ada yang disimpan.');

            return self::SUCCESS;
        }

        try {
            // Impor sebagian (--prefix) tidak boleh menonaktifkan wilayah di luar awalan itu.
            $stats = $import->execute($rows, $sourceRef, deactivateMissing: ! is_string($prefix) || $prefix === '');
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Selesai: %d baru, %d diperbarui, %d dinonaktifkan, %d tetap.', $stats['inserted'], $stats['updated'], $stats['deactivated'], $stats['unchanged']));

        return self::SUCCESS;
    }

    /** @return array<array-key, string> kode => nama (kode numerik seperti "11" menjadi kunci int) */
    public static function parse(string $file): array
    {
        $rows = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        $isCsv = str_ends_with(strtolower($file), '.csv');
        while (($line = fgets($handle)) !== false) {
            if ($isCsv) {
                $cols = str_getcsv(trim($line), escape: '');
                if (count($cols) >= 2 && is_string($cols[0]) && is_string($cols[1]) && RegionCode::isValid(trim($cols[0]))) {
                    $rows[trim($cols[0])] = trim($cols[1]);
                }

                continue;
            }

            // ('11.01','Kabupaten Aceh Selatan') — nama boleh berisi '' (kutip ter-escape).
            if (preg_match_all("/\\('([0-9.]+)'\\s*,\\s*'((?:[^'\\\\]|''|\\\\.)*)'\\)/", $line, $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $match) {
                    if (RegionCode::isValid($match[1])) {
                        $rows[$match[1]] = str_replace(["''", "\\'"], "'", $match[2]);
                    }
                }
            }
        }
        fclose($handle);

        return $rows;
    }
}
