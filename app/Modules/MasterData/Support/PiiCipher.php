<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Support;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Perlakuan NIK (UU 27/2022, docs/04 §5): tidak pernah plaintext di database.
 * - nik_hash: HMAC-SHA256 dengan pepper di env, untuk cari persis dan cegah ganda.
 * - nik_enc : AES-256-GCM dengan kunci BARA_PII_KEY (bukan APP_KEY), hanya dibuka lewat RevealNik.
 */
final class PiiCipher
{
    private ?Encrypter $encrypter = null;

    public function hash(string $nik): string
    {
        return hash_hmac('sha256', $nik, $this->pepper());
    }

    public function encrypt(string $nik): string
    {
        return $this->encrypter()->encryptString($nik);
    }

    public function decrypt(string $payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    /**
     * Validasi struktur NIK (Permendagri tentang administrasi kependudukan): 16 digit,
     * 6 digit kode wilayah, tanggal lahir DDMMYY (perempuan: DD + 40), 4 digit urut.
     */
    public static function validNik(string $nik): bool
    {
        if (preg_match('/^\d{16}$/', $nik) !== 1 || str_starts_with($nik, '00')) {
            return false;
        }

        $day = (int) substr($nik, 6, 2);
        $day = $day > 40 ? $day - 40 : $day;
        $month = (int) substr($nik, 8, 2);

        return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && substr($nik, 12, 4) !== '0000';
    }

    public static function mask(?string $last4): string
    {
        return $last4 === null ? '—' : '************'.$last4;
    }

    private function pepper(): string
    {
        $pepper = config()->string('bara.pii.nik_pepper');

        if (strlen($pepper) < 32) {
            throw new RuntimeException('BARA_NIK_PEPPER belum diatur (minimal 32 karakter acak).');
        }

        return $pepper;
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        $key = config()->string('bara.pii.key');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if (! is_string($raw) || strlen($raw) !== 32) {
            throw new RuntimeException('BARA_PII_KEY belum diatur. Buat dengan: php artisan bara:pii-key');
        }

        return $this->encrypter = new Encrypter($raw, 'aes-256-gcm');
    }
}
