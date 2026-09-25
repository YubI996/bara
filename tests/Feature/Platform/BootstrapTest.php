<?php

declare(strict_types=1);

use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('bootstrap membuat root Pemda, aplikasi core, entity organisasi, dan objek root', function (): void {
    $root = rootOrganization();

    expect($root->path)->toBe(config('bara.pemda.code'))
        ->and(DB::table('applications')->where('code', 'core')->value('owner_org_id'))->toBe($root->id)
        ->and(DB::table('objects')->where('id', $root->id)->value('owner_path'))->toBe($root->path);
});

test('bootstrap idempotent: menjalankan ulang tidak menggandakan data', function (): void {
    $this->seed(PlatformBootstrapSeeder::class);

    expect(DB::table('applications')->where('code', 'core')->count())->toBe(1)
        ->and(DB::table('core_organizations')->whereNull('parent_id')->count())->toBe(1);
});

test('extension PostgreSQL dan uuidv7 tersedia', function (): void {
    $row = DB::selectOne("SELECT uuidv7() AS id, 'a.b'::ltree AS path");

    expect($row->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/')
        ->and($row->path)->toBe('a.b');
});

test('audit log bersifat append-only di level database', function (): void {
    DB::table('audit_logs')->insert(['actor_type' => 'system', 'action' => 'test.append']);

    // Tiap percobaan dibungkus savepoint agar transaksi test tidak ikut batal.
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('action', 'test.append')->update(['action' => 'x'])))
        ->toThrow(QueryException::class, 'append-only');
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('action', 'test.append')->delete()))
        ->toThrow(QueryException::class, 'append-only');
});

test('migration menyiapkan partisi bulan berjalan sehingga audit tidak masuk DEFAULT', function (): void {
    $name = 'audit_logs_'.now('UTC')->format('Y_m');

    expect(DB::selectOne('SELECT to_regclass(?) AS t', [$name])->t)->toBe($name)
        ->and(DB::table('audit_logs_default')->count())->toBe(0);
});

test('perintah partisi audit membuat partisi bulan-bulan berikutnya', function (): void {
    $this->artisan('bara:audit-partitions', ['--months' => 6])->assertSuccessful();

    $name = 'audit_logs_'.now('UTC')->startOfMonth()->addMonths(6)->format('Y_m');
    expect(DB::selectOne('SELECT to_regclass(?) AS t', [$name])->t)->toBe($name);
});
