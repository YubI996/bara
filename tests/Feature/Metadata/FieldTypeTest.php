<?php

declare(strict_types=1);

use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Modules\Metadata\FieldTypes\SafeRegex;
use App\Modules\Metadata\Schema\FieldConfigValidator;
use App\Shared\Data\DataClassification;
use Illuminate\Support\Facades\Validator;

function definition(string $type, array $config = [], bool $required = false, string $code = 'nilai'): FieldDefinition
{
    return new FieldDefinition('01a0d8d1-0000-7000-8000-000000000001', $code, 'Nilai', null, $type, $required, false, false, false, DataClassification::Internal, $config, 1);
}

/** @return array{config: array<string, mixed>, errors: array<string, string>} */
function validateConfig(string $type, array $config, array $flags = []): array
{
    $field = new FieldDefinition('01a0d8d1-0000-7000-8000-000000000001', $flags['code'] ?? 'nilai', 'Nilai', null, $type, false, $flags['unique'] ?? false, $flags['indexed'] ?? false, $flags['searchable'] ?? false, DataClassification::Internal, [], 1);

    return app(FieldConfigValidator::class)->validate($field, $config);
}

function valuePasses(FieldDefinition $field, mixed $value): bool
{
    $type = app(FieldTypeRegistry::class)->get($field->type);
    $rules = [];
    foreach ($type->valueRules($field) as $suffix => $list) {
        $rules['v'.$suffix] = $list;
    }

    return Validator::make(['v' => $value], $rules)->passes();
}

test('registry memuat 15 tipe field M1 (tanpa geo_point)', function (): void {
    $codes = array_map(fn ($t) => $t->code(), app(FieldTypeRegistry::class)->all());

    expect($codes)->toHaveCount(15)
        ->toContain('string', 'text', 'rich_text', 'integer', 'decimal', 'money', 'percentage', 'boolean', 'date', 'datetime', 'enum', 'multi_enum', 'relationship', 'file', 'region')
        ->not->toContain('geo_point');
});

test('config default diterapkan dan kunci tak dikenal dibuang', function (): void {
    $result = validateConfig('string', ['max_length' => 100, 'hack' => 'x']);

    expect($result['errors'])->toBe([])
        ->and($result['config'])->toBe(['max_length' => 100]);
});

test('config tidak valid ditolak per tipe', function (string $type, array $config, string $errorKey): void {
    expect(validateConfig($type, $config)['errors'])->toHaveKey($errorKey);
})->with([
    'string terlalu panjang' => ['string', ['max_length' => 501], 'config.max_length'],
    'integer min > max' => ['integer', ['min' => 10, 'max' => 1], 'config.max'],
    'decimal scale > 6' => ['decimal', ['scale' => 7], 'config.scale'],
    'decimal min > max' => ['decimal', ['min' => '10.5', 'max' => '2'], 'config.max'],
    'enum tanpa opsi' => ['enum', ['options' => []], 'config.options'],
    'enum nilai opsi ganda' => ['enum', ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]], 'config.options'],
    'enum label opsi ganda' => ['enum', ['options' => [['value' => 'a', 'label' => 'Sama'], ['value' => 'b', 'label' => 'sama']]], 'config.options'],
    'enum nilai opsi berkoma' => ['enum', ['options' => [['value' => 'a,b', 'label' => 'A']]], 'config.options.0.value'],
    'enum default bukan opsi' => ['enum', ['options' => [['value' => 'a', 'label' => 'A']], 'default' => 'z'], 'config.default'],
    'date tidak nyata' => ['date', ['min' => '2026-02-30'], 'config.min'],
    'date min > max' => ['date', ['min' => '2026-12-01', 'max' => '2026-01-01'], 'config.max'],
    'file ekstensi berbahaya' => ['file', ['mimes' => ['php']], 'config.mimes.0'],
    'file terlalu besar' => ['file', ['mimes' => ['pdf'], 'max_kb' => 999999], 'config.max_kb'],
    'relasi tanpa target' => ['relationship', ['cardinality' => 'many_to_one'], 'config.target_entity_id'],
    'region level terbalik' => ['region', ['level_min' => 4, 'level_max' => 2], 'config.level_max'],
    'integer default di luar batas' => ['integer', ['min' => 1, 'max' => 5, 'default' => 9], 'config.default'],
]);

test('kombinasi flag tidak valid ditolak', function (): void {
    expect(validateConfig('text', [], ['indexed' => true])['errors'])->toHaveKey('is_indexed')
        ->and(validateConfig('string', [], ['unique' => true])['errors'])->toHaveKey('is_unique')
        ->and(validateConfig('integer', [], ['searchable' => true])['errors'])->toHaveKey('is_searchable');
});

test('kode field yang dicadangkan sistem ditolak', function (string $code): void {
    expect(validateConfig('string', [], ['code' => $code])['errors'])->toHaveKey('code');
})->with(['id', 'created_at', 'owner_org_id', 'title']);

test('pola regex berisiko ReDoS ditolak', function (string $pattern): void {
    expect(SafeRegex::error($pattern))->not->toBeNull();
})->with(['(a+)+', '(a*)*b', '([a-z]+)*$', '(.*a){20}', '(a)\1', '[unclosed']);

test('pola regex aman diterima dan dipakai validasi nilai', function (): void {
    expect(SafeRegex::error('[0-9]{16}'))->toBeNull();

    $field = definition('string', ['max_length' => 16, 'pattern' => '[0-9]{16}']);
    expect(valuePasses($field, '3201010101010001'))->toBeTrue()
        ->and(valuePasses($field, '32010101'))->toBeFalse();
});

test('aturan nilai tiap tipe', function (FieldDefinition $field, mixed $good, mixed $bad): void {
    expect(valuePasses($field, $good))->toBeTrue()
        ->and(valuePasses($field, $bad))->toBeFalse();
})->with([
    'integer batas' => [definition('integer', ['min' => 0, 'max' => 10]), 5, 11],
    'decimal skala' => [definition('decimal', ['scale' => 2]), '10.25', '10.255'],
    'money tidak negatif' => [definition('money', ['min' => '0', 'scale' => 2]), '1500000.50', '-1'],
    'percentage 0-100' => [definition('percentage', ['min' => '0', 'max' => '100', 'scale' => 2]), '99.5', '100.01'],
    'boolean' => [definition('boolean'), true, 'mungkin'],
    'date format' => [definition('date'), '2026-09-26', '26/09/2026'],
    'enum opsi' => [definition('enum', ['options' => [['value' => 'aktif', 'label' => 'Aktif']]]), 'aktif', 'lain'],
    'multi_enum elemen' => [definition('multi_enum', ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]), ['a', 'b'], ['a', 'x']],
    'relasi satu' => [definition('relationship', ['target_entity_id' => '01a0d8d1-0000-7000-8000-000000000009', 'cardinality' => 'many_to_one']), '01a0d8d1-0000-7000-8000-000000000009', 'bukan-uuid'],
    'string wajib' => [definition('string', ['max_length' => 5], true), 'abc', ''],
]);

test('json schema angka presisi memakai string, bukan float', function (): void {
    $schema = app(FieldTypeRegistry::class)->get('money')->jsonSchema(definition('money', ['scale' => 2], true));

    expect($schema['type'])->toBe('string')->and($schema['pattern'])->toBe('^-?\d+(\.\d{1,2})?$');
});

test('angka dari form (string) dinormalisasi menjadi integer', function (): void {
    expect(validateConfig('string', ['max_length' => '120'])['config'])->toBe(['max_length' => 120])
        ->and(validateConfig('file', ['mimes' => ['pdf'], 'max_files' => '3'])['config']['max_files'])->toBe(3);
});
