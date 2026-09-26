<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Metadata\Actions\CreateDraft;
use App\Modules\Metadata\Actions\ReviewDraftPrivacy;
use App\Modules\Metadata\Actions\SaveDraftField;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Shared\Data\DataClassification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->dishub = createOrganization('dishub');
    $this->monev = createApplication('monev');
    $this->entity = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama} {tahun}');
    addField($this->entity, 'nama', 'string', ['max_length' => 100], required: true);
    addField($this->entity, 'tahun', 'integer', ['min' => 2000, 'max' => 2100], required: true, indexed: true);
    addField($this->entity, 'pagu', 'money');
    addField($this->entity, 'status', 'enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'selesai', 'label' => 'Selesai']]], indexed: true);
    addField($this->entity, 'prioritas', 'boolean');
    addField($this->entity, 'catatan', 'rich_text');
    publishEntity($this->entity);
    $this->entity->refresh();
});

function runtimeUrl(string $name, array $params = []): string
{
    return route($name, ['app' => 'monev', 'entity' => 'kegiatan', ...$params]);
}

function fieldKey(Entity $entity, string $code): string
{
    return (string) DB::table('fields')->where('entity_version_id', $entity->published_version_id)->where('code', $code)->value('field_key');
}

function storeRecord(object $test, $user, array $data, ?string $owner = null)
{
    return $test->actingAs($user)->post(runtimeUrl('runtime.store'), ['owner_org_id' => $owner, 'data' => $data]);
}

function recordIdByTitle(string $title): string
{
    return (string) DB::table('records')->where('title', $title)->value('id');
}

test('entity terbit langsung punya halaman runtime tanpa deploy', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    $this->actingAs($operator)->get(runtimeUrl('runtime.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('runtime/index')->where('entity.name_plural', 'Kegiatan'));
    $this->actingAs($operator)->get(runtimeUrl('runtime.create'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('runtime/form')->has('fields', 6)->has('owners', 1));
    $this->actingAs($operator)->get(route('runtime.home'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('applications', 1));
});

test('operator menyimpan record: data berkunci field_key, judul, audit, dan event', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    storeRecord($this, $operator, [
        'nama' => '  Vaksinasi  ', 'tahun' => '2026', 'pagu' => '1.500.000,50', 'status' => 'rencana',
        'prioritas' => '1', 'catatan' => '<p>Aman</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
    ], $this->dinkes->id)->assertSessionHasNoErrors()->assertRedirect();

    $row = DB::table('records')->first();
    $data = json_decode($row->data, true);

    expect($row->title)->toBe('Vaksinasi 2026')
        ->and($data[fieldKey($this->entity, 'nama')])->toBe('Vaksinasi')
        ->and($data[fieldKey($this->entity, 'tahun')])->toBe(2026)
        ->and($data[fieldKey($this->entity, 'pagu')])->toBe('1500000.50')
        ->and($data[fieldKey($this->entity, 'prioritas')])->toBeTrue()
        ->and($data[fieldKey($this->entity, 'catatan')])->not->toContain('script')->not->toContain('javascript:')
        ->and(array_keys($data))->each->toMatch('/^[0-9a-f-]{36}$/')
        ->and(DB::table('objects')->where('id', $row->id)->value('owner_path'))->toBe($this->dinkes->path)
        ->and(DB::table('audit_logs')->where(['action' => 'record.create', 'object_id' => $row->id])->exists())->toBeTrue()
        ->and(DB::table('outbox_events')->where(['event_type' => 'record.created', 'aggregate_id' => $row->id])->exists())->toBeTrue();
});

test('payload dengan key tak dikenal ditolak 422', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    $this->actingAs($operator)->postJson(runtimeUrl('runtime.store'), [
        'owner_org_id' => $this->dinkes->id,
        'data' => ['nama' => 'A', 'tahun' => 2026, 'owner_org_id' => 'x', 'is_admin' => true],
    ])->assertStatus(422)->assertJsonValidationErrors('data');
});

test('validasi server per tipe', function (array $data, string $errorKey): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    storeRecord($this, $operator, ['nama' => 'A', 'tahun' => 2026, ...$data], $this->dinkes->id)
        ->assertSessionHasErrors($errorKey);
})->with([
    'wajib kosong' => [['nama' => ''], 'data.nama'],
    'integer bukan angka' => [['tahun' => 'dua ribu'], 'data.tahun'],
    'integer di luar batas' => [['tahun' => 1999], 'data.tahun'],
    'enum bukan opsi' => [['status' => 'batal'], 'data.status'],
    'uang negatif' => [['pagu' => '-5'], 'data.pagu'],
    'teks terlalu panjang' => [['nama' => str_repeat('a', 101)], 'data.nama'],
]);

test('unit pemilik di luar kewenangan ditolak', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    storeRecord($this, $operator, ['nama' => 'A', 'tahun' => 2026], $this->dishub->id)->assertSessionHasErrors('owner_org_id');
    expect(DB::table('records')->count())->toBe(0);
});

describe('isolasi antar-OPD (IDOR)', function (): void {
    beforeEach(function (): void {
        // Entity private: hanya unit pemilik yang boleh membaca.
        DB::table('entities')->where('id', $this->entity->id)->update(['default_visibility' => 'private']);
        $dishubOp = userWithAppRole($this->monev, 'operator', $this->dishub);
        storeRecord($this, $dishubOp, ['nama' => 'Rahasia Dishub', 'tahun' => 2026], $this->dishub->id)->assertSessionHasNoErrors();
        $this->recordId = recordIdByTitle('Rahasia Dishub 2026');
        $this->dinkesOp = userWithAppRole($this->monev, 'operator', $this->dinkes);
    });

    test('daftar tidak memuat record OPD lain', function (): void {
        $this->actingAs($this->dinkesOp)->get(runtimeUrl('runtime.index'))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 0));
    });

    test('setiap endpoint record OPD lain menjawab 404', function (): void {
        $params = ['record' => $this->recordId];
        $this->actingAs($this->dinkesOp)->get(runtimeUrl('runtime.show', $params))->assertNotFound();
        $this->actingAs($this->dinkesOp)->get(runtimeUrl('runtime.edit', $params))->assertNotFound();
        $this->actingAs($this->dinkesOp)->put(runtimeUrl('runtime.update', $params), ['lock_version' => 0, 'data' => ['nama' => 'X', 'tahun' => 2026]])->assertNotFound();
        $this->actingAs($this->dinkesOp)->delete(runtimeUrl('runtime.destroy', $params))->assertNotFound();

        expect(DB::table('records')->where('id', $this->recordId)->value('title'))->toBe('Rahasia Dishub 2026')
            ->and(DB::table('records')->where('id', $this->recordId)->value('deleted_at'))->toBeNull();
    });

    test('user tanpa role aplikasi mendapat 403', function (): void {
        $this->actingAs(User::factory()->create())->get(runtimeUrl('runtime.index'))->assertForbidden();
    });
});

test('visibilitas internal: OPD lain boleh membaca tetapi tidak mengubah', function (): void {
    $dinkesOp = userWithAppRole($this->monev, 'operator', $this->dinkes);
    storeRecord($this, $dinkesOp, ['nama' => 'Terbuka', 'tahun' => 2026], $this->dinkes->id);
    $id = recordIdByTitle('Terbuka 2026');
    $dishubOp = userWithAppRole($this->monev, 'operator', $this->dishub);

    $this->actingAs($dishubOp)->get(runtimeUrl('runtime.show', ['record' => $id]))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('can.update', false)->where('can.delete', false));
    $this->actingAs($dishubOp)->put(runtimeUrl('runtime.update', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Diubah', 'tahun' => 2026]])->assertNotFound();
});

test('viewer tidak bisa menambah data', function (): void {
    $viewer = userWithAppRole($this->monev, 'viewer', $this->dinkes);

    $this->actingAs($viewer)->get(runtimeUrl('runtime.create'))->assertForbidden();
    storeRecord($this, $viewer, ['nama' => 'A', 'tahun' => 2026], $this->dinkes->id)->assertForbidden();
});

test('konflik lock_version ditolak dengan pesan muat ulang', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    storeRecord($this, $operator, ['nama' => 'Awal', 'tahun' => 2026], $this->dinkes->id);
    $id = recordIdByTitle('Awal 2026');

    $this->actingAs($operator)->put(runtimeUrl('runtime.update', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Pertama', 'tahun' => 2026]])
        ->assertSessionHasNoErrors();
    $this->actingAs($operator)->put(runtimeUrl('runtime.update', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Kedua', 'tahun' => 2026]])
        ->assertSessionHasErrors('lock_version');

    expect(DB::table('records')->where('id', $id)->value('title'))->toBe('Pertama 2026')
        ->and((int) DB::table('records')->where('id', $id)->value('lock_version'))->toBe(1);

    $diff = json_decode((string) DB::table('audit_logs')->where(['action' => 'record.update', 'object_id' => $id])->value('changes'), true);
    expect($diff)->toBe(['nama' => ['Awal', 'Pertama']]);
});

test('nilai unik ditolak bila sudah dipakai', function (): void {
    $app = createApplication('kepeg');
    $entity = createEntity($app, 'pegawai', titleTemplate: '{nip}');
    app(SaveDraftField::class)->execute($entity->refresh(), new FieldInput(
        'nip', 'NIP', null, 'string', true, true, true, false, DataClassification::Internal, ['max_length' => 18],
    ));
    publishEntity($entity);
    $operator = userWithAppRole($app, 'operator');

    $this->actingAs($operator)->post(route('runtime.store', ['app' => 'kepeg', 'entity' => 'pegawai']), ['owner_org_id' => rootOrganization()->id, 'data' => ['nip' => '198001012000011001']])->assertSessionHasNoErrors();
    $this->actingAs($operator)->post(route('runtime.store', ['app' => 'kepeg', 'entity' => 'pegawai']), ['owner_org_id' => rootOrganization()->id, 'data' => ['nip' => '198001012000011001']])->assertSessionHasErrors('data.nip');
});

test('field data pribadi disembunyikan dari operator dan tidak terhapus saat operator mengubah record', function (): void {
    app(CreateDraft::class)->execute($this->entity->refresh());
    addField($this->entity, 'nik', 'string', ['max_length' => 16], classification: DataClassification::PersonalSpecific);
    addField($this->entity, 'telepon', 'string', ['max_length' => 20], classification: DataClassification::Personal);
    app(ReviewDraftPrivacy::class)->execute($this->entity->refresh(), userWithRole('dpo'));
    publishEntity($this->entity);

    $dpoEditor = userWithAppRole($this->monev, 'operator', $this->dinkes);
    DB::table('roles')->insert(['code' => 'petugas_pdp', 'application_id' => $this->monev->id, 'name' => 'Petugas data pribadi', 'clearance' => 'personal_specific', 'is_system' => false]);
    DB::table('role_assignments')->insert(['user_id' => $dpoEditor->id, 'role_id' => DB::table('roles')->where('code', 'petugas_pdp')->value('id'), 'scope_org_id' => $this->dinkes->id]);

    storeRecord($this, $dpoEditor, ['nama' => 'Warga', 'tahun' => 2026, 'nik' => '3201010101010001', 'telepon' => '081234567890'], $this->dinkes->id)->assertSessionHasNoErrors();
    $id = recordIdByTitle('Warga 2026');
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);

    $this->actingAs($operator)->get(runtimeUrl('runtime.show', ['record' => $id]))
        ->assertInertia(fn (Assert $p) => $p
            ->missing('record.values.nik')
            ->where('record.values.telepon', '0812****7890'));

    // Operator mengirim field terlarang → ditolak; tanpa field itu → nilai lama dipertahankan.
    $this->actingAs($operator)->put(runtimeUrl('runtime.update', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Warga', 'tahun' => 2026, 'nik' => 'x']])
        ->assertSessionHasErrors('data');
    $this->actingAs($operator)->put(runtimeUrl('runtime.update', ['record' => $id]), ['lock_version' => 0, 'data' => ['nama' => 'Warga Baru', 'tahun' => 2026]])
        ->assertSessionHasNoErrors();

    $data = json_decode((string) DB::table('records')->where('id', $id)->value('data'), true);
    expect($data[fieldKey($this->entity->refresh(), 'nik')])->toBe('3201010101010001');

    $audit = (string) DB::table('audit_logs')->where('object_id', $id)->where('action', 'record.create')->value('changes');
    expect($audit)->not->toContain('3201010101010001')->not->toContain('081234567890');
});

test('hapus lunak dan ditolak bila masih dirujuk relasi restrict', function (): void {
    $realisasi = createEntity($this->monev, 'realisasi', titleTemplate: '{uraian}');
    addField($realisasi, 'uraian', 'string', required: true);
    addField($realisasi, 'kegiatan', 'relationship', ['target_entity_id' => $this->entity->id, 'cardinality' => 'many_to_one']);
    publishEntity($realisasi);

    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    storeRecord($this, $operator, ['nama' => 'Induk', 'tahun' => 2026], $this->dinkes->id);
    $parent = recordIdByTitle('Induk 2026');
    $this->actingAs($operator)->post(route('runtime.store', ['app' => 'monev', 'entity' => 'realisasi']), ['owner_org_id' => $this->dinkes->id, 'data' => ['uraian' => 'Anak', 'kegiatan' => $parent]])
        ->assertSessionHasNoErrors();

    $this->actingAs($operator)->delete(runtimeUrl('runtime.destroy', ['record' => $parent]))->assertSessionHasErrors('record');

    $child = recordIdByTitle('Anak');
    $this->actingAs($operator)->delete(route('runtime.destroy', ['app' => 'monev', 'entity' => 'realisasi', 'record' => $child]))->assertRedirect();
    $this->actingAs($operator)->delete(runtimeUrl('runtime.destroy', ['record' => $parent]))->assertRedirect();

    expect(DB::table('records')->where('id', $parent)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('audit_logs')->where(['action' => 'record.delete', 'object_id' => $parent])->exists())->toBeTrue();
});

test('relasi ke data di luar kewenangan ditolak', function (): void {
    DB::table('entities')->where('id', $this->entity->id)->update(['default_visibility' => 'private']);
    $realisasi = createEntity($this->monev, 'realisasi', titleTemplate: '{uraian}');
    addField($realisasi, 'uraian', 'string', required: true);
    addField($realisasi, 'kegiatan', 'relationship', ['target_entity_id' => $this->entity->id, 'cardinality' => 'many_to_one']);
    publishEntity($realisasi);

    storeRecord($this, userWithAppRole($this->monev, 'operator', $this->dishub), ['nama' => 'Milik Dishub', 'tahun' => 2026], $this->dishub->id);
    $foreign = recordIdByTitle('Milik Dishub 2026');

    $this->actingAs(userWithAppRole($this->monev, 'operator', $this->dinkes))
        ->post(route('runtime.store', ['app' => 'monev', 'entity' => 'realisasi']), ['owner_org_id' => $this->dinkes->id, 'data' => ['uraian' => 'Curang', 'kegiatan' => $foreign]])
        ->assertSessionHasErrors('data.kegiatan');
});

describe('lampiran berkas', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        app(CreateDraft::class)->execute($this->entity->refresh());
        addField($this->entity, 'bukti', 'file', ['mimes' => ['pdf'], 'max_files' => 2, 'max_kb' => 100]);
        publishEntity($this->entity);
        $this->operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    });

    test('PDF diterima, disimpan acak, dan hanya bisa diunduh dalam scope', function (): void {
        $this->actingAs($this->operator)->post(runtimeUrl('runtime.store'), [
            'owner_org_id' => $this->dinkes->id,
            'data' => ['nama' => 'Berkas', 'tahun' => 2026],
            'files' => ['bukti' => [UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf')]],
        ])->assertSessionHasNoErrors();

        $file = DB::table('files')->first();
        $id = recordIdByTitle('Berkas 2026');
        expect($file->original_name)->toBe('laporan.pdf')->and($file->path)->not->toContain('laporan');

        $this->actingAs($this->operator)->get(runtimeUrl('runtime.files.download', ['record' => $id, 'file' => $file->id]))->assertOk();

        DB::table('entities')->where('id', $this->entity->id)->update(['default_visibility' => 'private']);
        DB::table('objects')->where('id', $id)->update(['visibility' => 'private']);
        $other = userWithAppRole($this->monev, 'operator', $this->dishub);
        $this->actingAs($other)->get(runtimeUrl('runtime.files.download', ['record' => $id, 'file' => $file->id]))->assertNotFound();
    });

    test('berkas berekstensi atau isi terlarang ditolak', function (UploadedFile $upload): void {
        $this->actingAs($this->operator)->post(runtimeUrl('runtime.store'), [
            'owner_org_id' => $this->dinkes->id,
            'data' => ['nama' => 'Jahat', 'tahun' => 2026],
            'files' => ['bukti' => [$upload]],
        ])->assertSessionHasErrors();

        expect(DB::table('files')->count())->toBe(0);
    })->with([
        'php' => fn () => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET[1]);'),
        // Berkas sungguhan (bukan fake) agar MIME dideteksi dari isi, seperti pada request nyata.
        'php menyamar pdf' => function (): UploadedFile {
            $path = tempnam(sys_get_temp_dir(), 'bara');
            file_put_contents($path, '<?php system($_GET[1]); ?>');

            return new UploadedFile($path, 'laporan.pdf', null, null, true);
        },
        'terlalu besar' => fn () => UploadedFile::fake()->create('besar.pdf', 500, 'application/pdf'),
    ]);
});

test('daftar memakai jumlah query konstan (tanpa N+1)', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    $count = function () use ($operator): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($operator)->get(runtimeUrl('runtime.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    storeRecord($this, $operator, ['nama' => 'Satu', 'tahun' => 2026], $this->dinkes->id);
    $one = $count();
    foreach (range(1, 15) as $i) {
        storeRecord($this, $operator, ['nama' => "Data {$i}", 'tahun' => 2026], $this->dinkes->id);
    }

    expect($count())->toBe($one);
});

test('filter field terindeks dan pencarian judul', function (): void {
    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    storeRecord($this, $operator, ['nama' => 'Posyandu', 'tahun' => 2026, 'status' => 'selesai'], $this->dinkes->id);
    storeRecord($this, $operator, ['nama' => 'Imunisasi', 'tahun' => 2025, 'status' => 'rencana'], $this->dinkes->id);

    $this->actingAs($operator)->get(runtimeUrl('runtime.index', ['f' => ['status' => 'selesai']]))
        ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.title', 'Posyandu 2026'));
    $this->actingAs($operator)->get(runtimeUrl('runtime.index', ['q' => 'imunisasi']))
        ->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    $this->actingAs($operator)->get(runtimeUrl('runtime.index', ['q' => '%']))
        ->assertInertia(fn (Assert $p) => $p->has('rows', 0));
});

test('publikasi membuat index ekspresi untuk field terindeks', function (): void {
    $indexes = DB::table('pg_indexes')->where('tablename', 'records')->where('indexname', 'like', 'rf\_%')->pluck('indexdef')->implode("\n");

    expect($indexes)->toContain(fieldKey($this->entity, 'tahun'))->toContain('bara_to_bigint')
        ->and($indexes)->toContain(fieldKey($this->entity, 'status'));
});

test('entity dari aplikasi tidak aktif tidak punya halaman', function (): void {
    Application::query()->whereKey($this->monev->id)->update(['status' => 'archived']);

    $this->actingAs(userWithAppRole($this->monev, 'operator', $this->dinkes))->get(runtimeUrl('runtime.index'))->assertNotFound();
});
