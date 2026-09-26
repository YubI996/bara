<?php

declare(strict_types=1);

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Schema\ChangeClassifier;
use App\Shared\Data\DataClassification;

function fd(string $key, array $overrides = []): FieldDefinition
{
    $base = [
        'field_key' => $key, 'code' => 'kode_'.substr($key, -1), 'label' => 'Label '.substr($key, -1), 'help_text' => null,
        'type' => 'string', 'required' => false, 'unique' => false, 'indexed' => false, 'searchable' => false,
        'classification' => 'internal', 'config' => ['max_length' => 100], 'position' => 1,
    ];

    return FieldDefinition::fromArray([...$base, ...$overrides]);
}

function categoriesOf(array $previous, array $draft): array
{
    return array_map(fn ($c) => [$c->kind, $c->category->value], app(ChangeClassifier::class)->classify($previous, $draft));
}

test('publikasi pertama: semua field aman', function (): void {
    expect(categoriesOf([], [fd('k1', ['required' => true])]))->toBe([['added', 'safe']]);
});

test('tanpa perubahan menghasilkan daftar kosong', function (): void {
    expect(categoriesOf([fd('k1')], [fd('k1')]))->toBe([]);
});

test('kategori perubahan sesuai docs/06 §4', function (array $old, array $new, array $expected): void {
    expect(categoriesOf([fd('k0'), ...$old], [fd('k0'), ...$new]))->toBe($expected);
})->with([
    'tambah field opsional' => [[], [fd('k1')], [['added', 'safe']]],
    'tambah field wajib tanpa default' => [[], [fd('k1', ['required' => true])], [['added', 'blocked']]],
    'tambah field wajib dengan default' => [[], [fd('k1', ['required' => true, 'config' => ['max_length' => 10, 'default' => 'x']])], [['added', 'migration']]],
    'ganti kode (field_key sama)' => [[fd('k1')], [fd('k1', ['code' => 'kode_baru'])], [['modified', 'safe']]],
    'ganti label' => [[fd('k1')], [fd('k1', ['label' => 'Baru'])], [['modified', 'safe']]],
    'tipe kompatibel integer→decimal' => [[fd('k1', ['type' => 'integer', 'config' => []])], [fd('k1', ['type' => 'decimal', 'config' => ['scale' => 2]])], [['modified', 'migration']]],
    'tipe tidak kompatibel string→integer' => [[fd('k1')], [fd('k1', ['type' => 'integer', 'config' => []])], [['modified', 'blocked']]],
    'hapus field' => [[fd('k1')], [], [['removed', 'warning']]],
    'perketat panjang' => [[fd('k1')], [fd('k1', ['config' => ['max_length' => 50]])], [['modified', 'warning']]],
    'longgarkan panjang' => [[fd('k1')], [fd('k1', ['config' => ['max_length' => 200]])], [['modified', 'safe']]],
    'menjadi wajib' => [[fd('k1')], [fd('k1', ['required' => true])], [['modified', 'warning']]],
    'klasifikasi berubah' => [[fd('k1')], [fd('k1', ['classification' => 'personal'])], [['modified', 'warning']]],
]);

test('target relasi tidak bisa diganti', function (): void {
    $rel = fn (string $target) => fd('k1', ['type' => 'relationship', 'config' => ['target_entity_id' => $target, 'cardinality' => 'many_to_one']]);

    expect(categoriesOf([$rel('a')], [$rel('b')]))->toBe([['modified', 'blocked']]);
});

test('opsi enum yang dihapus diberi peringatan', function (): void {
    $enum = fn (array $values) => fd('k1', ['type' => 'enum', 'config' => ['options' => array_map(fn ($v) => ['value' => $v, 'label' => strtoupper($v)], $values)]]);
    $changes = app(ChangeClassifier::class)->classify([$enum(['a', 'b'])], [$enum(['a'])]);

    expect($changes[0]->category->value)->toBe('warning')
        ->and($changes[0]->messages[0])->toContain('b');
});

test('klasifikasi DataClassification urut sensitivitas', function (): void {
    expect(DataClassification::PersonalSpecific->rank())->toBeGreaterThan(DataClassification::Personal->rank())
        ->and(DataClassification::Personal->isPersonal())->toBeTrue()
        ->and(DataClassification::Restricted->isPersonal())->toBeFalse();
});
