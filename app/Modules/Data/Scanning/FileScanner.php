<?php

declare(strict_types=1);

namespace App\Modules\Data\Scanning;

/**
 * Pemindai malware untuk lampiran (docs/05 §4 kontrol 4). Implementasi tidak boleh melempar
 * exception untuk kegagalan pindai; kembalikan ScanResult::error agar berkas tetap tertahan.
 */
interface FileScanner
{
    /** @param  resource  $stream */
    public function scan($stream): ScanResult;
}
