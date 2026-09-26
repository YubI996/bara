<?php

declare(strict_types=1);

namespace App\Modules\Data\Scanning;

/**
 * Klien clamd lewat protokol INSTREAM (TCP atau unix socket). Berkas dikirim per potongan
 * dengan prefiks panjang 4 byte big-endian dan diakhiri potongan kosong; clamd menjawab
 * "stream: OK" atau "stream: <signature> FOUND".
 */
final readonly class ClamdScanner implements FileScanner
{
    private const int CHUNK = 8192;

    public function __construct(
        private string $socket,
        private float $timeoutSeconds,
    ) {}

    public function scan($stream): ScanResult
    {
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client($this->socket, $errno, $errstr, $this->timeoutSeconds);

        if ($conn === false) {
            return ScanResult::error("clamd tidak terjangkau: {$errstr}");
        }

        try {
            stream_set_timeout($conn, (int) ceil($this->timeoutSeconds));
            fwrite($conn, "zINSTREAM\0");

            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);
                if ($chunk === false) {
                    return ScanResult::error('Gagal membaca berkas.');
                }
                if ($chunk === '') {
                    continue;
                }
                if (fwrite($conn, pack('N', strlen($chunk)).$chunk) === false) {
                    return ScanResult::error('Koneksi clamd terputus.');
                }
            }

            fwrite($conn, pack('N', 0));
            $reply = trim((string) stream_get_contents($conn), "\0\r\n ");
        } finally {
            fclose($conn);
        }

        return self::parse($reply);
    }

    public static function parse(string $reply): ScanResult
    {
        if (preg_match('/^stream: OK$/', $reply) === 1) {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m) === 1) {
            return ScanResult::infected($m[1]);
        }

        return ScanResult::error($reply === '' ? 'Jawaban clamd kosong.' : $reply);
    }
}
