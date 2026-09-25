<?php

declare(strict_types=1);

namespace App\Modules\Audit\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuat partisi bulanan audit_logs beberapa bulan ke depan (ADR 0011).
 */
final class EnsureAuditPartitions extends Command
{
    protected $signature = 'bara:audit-partitions {--months=3 : Jumlah bulan ke depan}';

    protected $description = 'Buat partisi bulanan tabel audit_logs';

    public function handle(): int
    {
        $months = max(0, (int) $this->option('months'));
        $start = CarbonImmutable::now('UTC')->startOfMonth();

        for ($i = 0; $i <= $months; $i++) {
            $from = $start->addMonths($i);
            $to = $from->addMonth();
            // Nama tabel dari tanggal terformat (bukan input pengguna).
            $name = 'audit_logs_'.$from->format('Y_m');

            if (DB::scalar('SELECT to_regclass(?)::text', [$name]) !== null) {
                $this->line("Partisi {$name} sudah ada.");

                continue;
            }

            // Baris bulan ini yang telanjur masuk DEFAULT membuat CREATE PARTITION gagal.
            // Audit append-only tidak boleh dipindah otomatis: laporkan untuk ditangani DBA.
            $stray = DB::table('audit_logs_default')
                ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->exists();

            if ($stray) {
                $this->error("Partisi {$name} tidak dibuat: audit_logs_default berisi baris periode ini. Tangani manual (lihat docs/13).");

                continue;
            }

            DB::statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF audit_logs FOR VALUES FROM ('%s') TO ('%s')",
                $name,
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ));
            $this->line("Partisi {$name} siap.");
        }

        return self::SUCCESS;
    }
}
