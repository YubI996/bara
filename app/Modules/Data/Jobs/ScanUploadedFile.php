<?php

declare(strict_types=1);

namespace App\Modules\Data\Jobs;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Scanning\FileScanner;
use App\Modules\Data\Scanning\ScanResult;
use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memindai lampiran berstatus 'pending' lalu menetapkan clean/infected/error. Hanya 'clean'
 * yang boleh diunduh. Berkas terinfeksi dihapus dari disk; barisnya tetap untuk jejak audit.
 */
final class ScanUploadedFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public string $fileId) {}

    public function handle(ConnectionInterface $db, Filesystems $filesystems, FileScanner $scanner, AuditLogger $audit, EventRecorder $events): void
    {
        $file = $db->table('files')->where('id', $this->fileId)->where('scan_status', 'pending')->first(['id', 'object_id', 'disk', 'path']);

        if (! is_object($file) || ! is_string($file->disk ?? null) || ! is_string($file->path ?? null)) {
            return;
        }

        $stream = $filesystems->disk($file->disk)->readStream($file->path);
        $result = is_resource($stream) ? $scanner->scan($stream) : ScanResult::error('Berkas tidak ditemukan di penyimpanan.');

        if (is_resource($stream)) {
            fclose($stream);
        }

        // Kegagalan pindai dicoba ulang dulu; status 'error' baru ditulis di percobaan terakhir.
        if ($result->status === 'error' && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);

            return;
        }

        $db->transaction(function () use ($db, $file, $result, $audit, $events): void {
            $db->table('files')->where('id', $this->fileId)->update([
                'scan_status' => $result->status,
                'scan_detail' => $result->detail,
                'scanned_at' => now(),
            ]);

            $objectId = is_string($file->object_id ?? null) ? $file->object_id : null;
            $audit->log('file.scanned', $objectId, 'record', ['scan_status' => ['pending', $result->status]], ['file_id' => $this->fileId]);

            if ($result->status === 'infected') {
                $events->record('file.infected', 'file', $this->fileId, ['object_id' => $objectId]);
            }
        });

        if ($result->status === 'infected') {
            $filesystems->disk($file->disk)->delete($file->path);
        }
    }
}
