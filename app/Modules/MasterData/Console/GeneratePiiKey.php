<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Console;

use Illuminate\Console\Command;

final class GeneratePiiKey extends Command
{
    protected $signature = 'bara:pii-key';

    protected $description = 'Cetak nilai acak baru untuk BARA_PII_KEY dan BARA_NIK_PEPPER (salin ke .env / secret manager).';

    public function handle(): int
    {
        $this->line('BARA_PII_KEY=base64:'.base64_encode(random_bytes(32)));
        $this->line('BARA_NIK_PEPPER='.bin2hex(random_bytes(32)));
        $this->warn('Simpan di secret manager. Kehilangan kunci = NIK terenkripsi tidak bisa dibuka; mengganti pepper = nik_hash harus dihitung ulang.');

        return self::SUCCESS;
    }
}
