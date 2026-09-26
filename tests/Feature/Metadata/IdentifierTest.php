<?php

declare(strict_types=1);

use App\Shared\Validation\Identifier;

test('identifier valid diterima', function (string $code): void {
    expect(Identifier::isValid($code))->toBeTrue();
})->with(['nama', 'tahun_anggaran', 'nilai2', 'ab']);

test('identifier berbahaya atau salah format ditolak', function (mixed $code): void {
    expect(Identifier::isValid($code))->toBeFalse();
})->with([
    'injeksi titik koma' => 'year; DROP TABLE records;--',
    'operator JSONB' => "data->>'x'",
    'homoglyph kiril (а)' => 'dаta',
    'homoglyph kiril awal' => 'сode',
    'zero-width space' => "nama\u{200B}",
    'huruf besar' => 'Nama',
    'spasi' => 'nama lengkap',
    'tanda hubung' => 'nama-lengkap',
    'titik' => 'a.b',
    'diawali angka' => '1nama',
    'diawali underscore' => '_id',
    'satu karakter' => 'a',
    'terlalu panjang' => str_repeat('a', 64),
    'kata kunci SQL' => 'select',
    'kata kunci SQL lain' => 'drop',
    'null byte' => "nama\0",
    'bukan string' => 123,
]);
