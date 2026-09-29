<?php

declare(strict_types=1);

use App\Modules\Data\Jobs\EnsureRecordIndexes;
use App\Modules\Metadata\Actions\CreateDraft;
use App\Shared\Data\DataClassification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * M3 — Relationship: m2o & m2m, pencarian async, kolom relasi tanpa N+1, restrict/nullify,
 * index unik m2o, navigasi balik.
 */
beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->dishub = createOrganization('dishub');
    $this->monev = createApplication('monev');

    $this->program = createEntity($this->monev, 'program', titleTemplate: '{nama}');
    addField($this->program, 'nama', 'string', required: true);
    publishEntity($this->program);

    $this->sasaran = createEntity($this->monev, 'sasaran', titleTemplate: '{nama}');
    addField($this->sasaran, 'nama', 'string', required: true);
    publishEntity($this->sasaran);

    $this->kegiatan = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama}');
    addField($this->kegiatan, 'nama', 'string', required: true);
    addField($this->kegiatan, 'program', 'relationship', ['target_entity_id' => $this->program->id, 'cardinality' => 'many_to_one', 'on_target_delete' => 'restrict']);
    addField($this->kegiatan, 'sasaran', 'relationship', ['target_entity_id' => $this->sasaran->id, 'cardinality' => 'many_to_many', 'on_target_delete' => 'nullify']);
    addField($this->kegiatan, 'unit', 'relationship', ['target_entity_id' => DB::table('entities')->where('code', 'organization')->value('id'), 'cardinality' => 'many_to_one', 'on_target_delete' => 'restrict']);
    publishEntity($this->kegiatan);

    $this->operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
});

function relUrl(string $name, string $entity, array $params = []): string
{
    return route($name, ['app' => 'monev', 'entity' => $entity, ...$params]);
}

function makeRecord(object $test, $user, string $entity, array $data, string $owner): string
{
    $test->actingAs($user)->post(relUrl('runtime.store', $entity), ['owner_org_id' => $owner, 'data' => $data])
        ->assertSessionHasNoErrors();

    return (string) DB::table('records')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

describe('pencarian async (lookup)', function (): void {
    test('minimal 2 karakter, hasil dibatasi cakupan pembaca', function (): void {
        makeRecord($this, $this->operator, 'program', ['nama' => 'Program Sehat'], $this->dinkes->id);
        $dishubOp = userWithAppRole($this->monev, 'operator', $this->dishub);
        $private = makeRecord($this, $dishubOp, 'program', ['nama' => 'Program Sehat Dishub'], $this->dishub->id);
        DB::table('objects')->where('id', $private)->update(['visibility' => 'private']);

        $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'program', 'q' => 's']))
            ->assertOk()->assertJson(['options' => []]);

        $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'program', 'q' => 'sehat']))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.label', 'Program Sehat');

        // Entity Core (unit organisasi) dicari dari nama unit.
        $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'unit', 'q' => 'dinkes']))
            ->assertOk()->assertJsonPath('options.0.value', $this->dinkes->id);
    });

    test('hanya field relasi yang boleh ditulis, oleh user yang boleh menambah/mengubah', function (): void {
        $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'nama', 'q' => 'ab']))->assertNotFound();
        $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'tidak_ada', 'q' => 'ab']))->assertNotFound();

        $viewer = userWithAppRole($this->monev, 'viewer', $this->dinkes);
        $this->actingAs($viewer)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'program', 'q' => 'ab']))->assertForbidden();

        auth()->guard('web')->logout();
        $this->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'program', 'q' => 'ab']))->assertUnauthorized();
    });

    test('form tidak lagi memuat ratusan opsi relasi di props', function (): void {
        makeRecord($this, $this->operator, 'program', ['nama' => 'Program A'], $this->dinkes->id);

        $this->actingAs($this->operator)->get(relUrl('runtime.create', 'kegiatan'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('fields.1.code', 'program')
                ->where('fields.1.options', [])
                ->where('fields.1.lookup_url', '/apps/monev/kegiatan/lookup/program'));
    });
});

test('many-to-many: simpan, ubah, dan kosongkan pilihan', function (): void {
    $a = makeRecord($this, $this->operator, 'sasaran', ['nama' => 'Anak'], $this->dinkes->id);
    $b = makeRecord($this, $this->operator, 'sasaran', ['nama' => 'Lansia'], $this->dinkes->id);
    $id = makeRecord($this, $this->operator, 'kegiatan', ['nama' => 'Posyandu', 'sasaran' => [$a, $b]], $this->dinkes->id);

    $this->actingAs($this->operator)->get(relUrl('runtime.show', 'kegiatan', ['record' => $id]))
        ->assertInertia(fn (Assert $p) => $p->where('record.values.sasaran.0.label', 'Anak')->where('record.values.sasaran.1.label', 'Lansia'));

    $this->actingAs($this->operator)->put(relUrl('runtime.update', 'kegiatan', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Posyandu', 'sasaran' => [$b]]])
        ->assertSessionHasNoErrors();
    expect(DB::table('record_links')->where('source_id', $id)->pluck('target_id')->all())->toBe([$b]);

    // Tanpa key = semua pilihan dilepas (form mengirim nol hidden input).
    $this->actingAs($this->operator)->put(relUrl('runtime.update', 'kegiatan', ['record' => $id]), ['lock_version' => 1, 'data' => ['nama' => 'Posyandu']])
        ->assertSessionHasNoErrors();
    expect(DB::table('record_links')->where('source_id', $id)->count())->toBe(0);
});

test('hapus target: restrict menolak dengan menyebut perujuk, nullify melepas tautan', function (): void {
    $program = makeRecord($this, $this->operator, 'program', ['nama' => 'Program Gizi'], $this->dinkes->id);
    $sasaran = makeRecord($this, $this->operator, 'sasaran', ['nama' => 'Balita'], $this->dinkes->id);
    $kegiatan = makeRecord($this, $this->operator, 'kegiatan', ['nama' => 'PMT', 'program' => $program, 'sasaran' => [$sasaran]], $this->dinkes->id);

    $this->actingAs($this->operator)->delete(relUrl('runtime.destroy', 'program', ['record' => $program]))
        ->assertSessionHasErrors(['record' => 'Data ini masih dirujuk oleh 1 Kegiatan sehingga tidak dapat dihapus. Ubah atau hapus data yang merujuk terlebih dahulu.']);
    expect(DB::table('records')->where('id', $program)->value('deleted_at'))->toBeNull();

    $this->actingAs($this->operator)->delete(relUrl('runtime.destroy', 'sasaran', ['record' => $sasaran]))->assertSessionHasNoErrors();
    expect(DB::table('record_links')->where('source_id', $kegiatan)->where('target_id', $sasaran)->exists())->toBeFalse()
        ->and(DB::table('record_links')->where('source_id', $kegiatan)->where('target_id', $program)->exists())->toBeTrue();
});

test('daftar dengan 3 kolom relasi memakai jumlah query konstan (tanpa N+1)', function (): void {
    $program = makeRecord($this, $this->operator, 'program', ['nama' => 'Program X'], $this->dinkes->id);
    $s1 = makeRecord($this, $this->operator, 'sasaran', ['nama' => 'S1'], $this->dinkes->id);
    $s2 = makeRecord($this, $this->operator, 'sasaran', ['nama' => 'S2'], $this->dinkes->id);
    $add = fn (int $i) => makeRecord($this, $this->operator, 'kegiatan', ['nama' => "Kegiatan {$i}", 'program' => $program, 'sasaran' => [$s1, $s2], 'unit' => $this->dinkes->id], $this->dinkes->id);

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->operator)->get(relUrl('runtime.index', 'kegiatan'))->assertOk();

        return count(DB::getQueryLog());
    };

    $add(1);
    $one = $count();
    foreach (range(2, 20) as $i) {
        $add($i);
    }

    expect($count())->toBe($one);
    $this->actingAs($this->operator)->get(relUrl('runtime.index', 'kegiatan'))
        ->assertInertia(fn (Assert $p) => $p
            ->where('rows.0.values.program.0.label', 'Program X')
            ->where('rows.0.values.sasaran.1.label', 'S2')
            ->where('rows.0.values.unit.0.label', $this->dinkes->name));
});

test('index unik m2o per relationship menolak dua tautan untuk satu source di level DB', function (): void {
    config(['bara.records.concurrent_index' => false]);
    app()->call([new EnsureRecordIndexes($this->kegiatan->id), 'handle']);

    $relationship = (string) DB::table('relationships')->where('source_entity_id', $this->kegiatan->id)->where('code', 'program')->value('id');
    $index = 'rl_m2o_'.substr(hash('sha256', $relationship), 0, 24);
    expect(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeTrue();

    $p1 = makeRecord($this, $this->operator, 'program', ['nama' => 'P1'], $this->dinkes->id);
    $p2 = makeRecord($this, $this->operator, 'program', ['nama' => 'P2'], $this->dinkes->id);
    $k = makeRecord($this, $this->operator, 'kegiatan', ['nama' => 'K', 'program' => $p1], $this->dinkes->id);

    // Savepoint: kegagalan yang diharapkan tidak membatalkan transaksi tes.
    expect(fn () => DB::transaction(fn () => DB::table('record_links')->insert(['relationship_id' => $relationship, 'source_id' => $k, 'target_id' => $p2, 'position' => 1])))
        ->toThrow(QueryException::class);

    // Relasi m2m tidak mendapat index unik.
    $m2m = (string) DB::table('relationships')->where('source_entity_id', $this->kegiatan->id)->where('code', 'sasaran')->value('id');
    expect(DB::table('pg_indexes')->where('indexname', 'rl_m2o_'.substr(hash('sha256', $m2m), 0, 24))->exists())->toBeFalse();
});

test('navigasi balik "Dirujuk oleh" dibatasi cakupan pembaca', function (): void {
    $program = makeRecord($this, $this->operator, 'program', ['nama' => 'Program Bersama'], $this->dinkes->id);
    makeRecord($this, $this->operator, 'kegiatan', ['nama' => 'Kegiatan Dinkes', 'program' => $program], $this->dinkes->id);

    $dishubOp = userWithAppRole($this->monev, 'operator', $this->dishub);
    $private = makeRecord($this, $dishubOp, 'kegiatan', ['nama' => 'Kegiatan Rahasia Dishub', 'program' => $program], $this->dishub->id);
    DB::table('objects')->where('id', $private)->update(['visibility' => 'private']);

    $this->actingAs($this->operator)->get(relUrl('runtime.show', 'program', ['record' => $program]))
        ->assertInertia(fn (Assert $p) => $p
            ->has('referenced_by', 1)
            ->where('referenced_by.0.count', 1)
            ->where('referenced_by.0.items.0.title', 'Kegiatan Dinkes'))
        ->assertDontSee('Kegiatan Rahasia Dishub');
});

test('field relasi berklasifikasi di atas clearance tidak bisa dicari', function (): void {
    app(CreateDraft::class)->execute($this->kegiatan->refresh());
    addField($this->kegiatan, 'mitra', 'relationship', ['target_entity_id' => $this->program->id, 'cardinality' => 'many_to_one'], classification: DataClassification::Restricted);
    publishEntity($this->kegiatan);

    $this->actingAs($this->operator)->getJson(relUrl('runtime.lookup', 'kegiatan', ['field' => 'mitra', 'q' => 'ab']))->assertNotFound();
});
