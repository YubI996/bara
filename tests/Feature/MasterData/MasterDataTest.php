<?php

declare(strict_types=1);

use App\Modules\MasterData\Actions\ImportRegions;
use App\Modules\MasterData\Console\ImportRegionsCommand;
use App\Modules\MasterData\Support\PiiCipher;
use App\Modules\Metadata\Actions\ReviewDraftPrivacy;
use App\Shared\Data\DataClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

const SAMPLE_REGIONS = [
    '64' => 'Kalimantan Timur',
    '64.72' => 'Kota Samarinda',
    '64.72.01' => 'Palaran',
    '64.72.01.1001' => 'Rawa Makmur',
    '64.72.01.1002' => 'Handil Bakti',
];

function importSample(array $rows = SAMPLE_REGIONS, string $ref = 'Kepmendagri 300.2.2-2430 Tahun 2025'): array
{
    return app(ImportRegions::class)->execute($rows, $ref);
}

beforeEach(function (): void {
    $this->walidata = userWithRole('data_steward');
});

describe('wilayah', function (): void {
    test('impor menurunkan level & induk, mencatat source_ref, idempoten, dan menonaktifkan kode yang hilang', function (): void {
        expect(importSample())->toMatchArray(['inserted' => 5, 'updated' => 0]);
        $desa = DB::table('core_regions')->where('code', '64.72.01.1001')->first();
        expect($desa->level)->toBe(4)
            ->and(DB::table('core_regions')->where('id', $desa->parent_id)->value('code'))->toBe('64.72.01')
            ->and($desa->source_ref)->toBe('Kepmendagri 300.2.2-2430 Tahun 2025')
            ->and(DB::table('objects')->where('id', $desa->id)->value('visibility'))->toBe('public');

        expect(importSample())->toMatchArray(['inserted' => 0, 'unchanged' => 5]);

        $renamed = SAMPLE_REGIONS;
        $renamed['64.72.01.1002'] = 'Handil Bhakti';
        unset($renamed['64.72.01.1001']);
        expect(importSample($renamed))->toMatchArray(['updated' => 1, 'deactivated' => 1]);
        expect(DB::table('core_regions')->where('code', '64.72.01.1001')->value('valid_to'))->not->toBeNull();
    });

    test('baris tanpa induk atau kode salah ditolak seluruhnya', function (): void {
        expect(fn () => importSample(['64.72.01' => 'Palaran']))->toThrow(InvalidArgumentException::class, 'Induk 64.72');
        expect(fn () => importSample(['6' => 'Salah']))->toThrow(InvalidArgumentException::class);
        expect(DB::table('core_regions')->count())->toBe(0);
    });

    test('perintah impor membaca dump SQL maupun CSV dan menautkan Pemda ke kodenya', function (): void {
        config(['bara.pemda.region_code' => '64.72']);
        $sql = tempnam(sys_get_temp_dir(), 'wil').'.sql';
        file_put_contents($sql, "INSERT INTO wilayah (kode, nama)\nVALUES\n('64','Kalimantan Timur'),\n('64.72','Kota Samarinda'),\n('64.72.01','Palaran'),\n('64.72.01.1001','Rawa Makmur');\n");
        $csv = tempnam(sys_get_temp_dir(), 'wil').'.csv';
        file_put_contents($csv, "kode,nama\n64,Kalimantan Timur\n64.72,Kota Samarinda\n");

        expect(ImportRegionsCommand::parse($csv))->toHaveCount(2);
        $this->artisan('bara:import-regions', ['file' => $sql])->assertFailed();
        $this->artisan('bara:import-regions', ['file' => $sql, '--source-ref' => 'Kepmendagri 300.2.2-2430 Tahun 2025'])->assertSuccessful();

        expect(DB::table('core_regions')->count())->toBe(4)
            ->and(DB::table('core_organizations')->where('id', rootOrganization()->id)->value('region_id'))
            ->toBe(DB::table('core_regions')->where('code', '64.72')->value('id'));
    });

    test('halaman wilayah: jelajah per tingkat dan cari', function (): void {
        importSample();
        $this->actingAs($this->walidata)->get(route('admin.master-data.regions'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/master-data/regions')->has('items', 1)->where('items.0.code', '64'));
        $this->actingAs($this->walidata)->get(route('admin.master-data.regions', ['parent' => '64.72.01']))
            ->assertInertia(fn (Assert $p) => $p->has('items', 2)->has('trail', 3));
        $this->actingAs($this->walidata)->get(route('admin.master-data.regions', ['q' => 'makmur']))
            ->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.code', '64.72.01.1001'));
    });
});

test('tahun anggaran: hanya satu berjalan, urutan status ditegakkan, hanya pengelola yang boleh', function (): void {
    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.store'), ['year' => 2026, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertSessionHasNoErrors();
    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.store'), ['year' => 2027, 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])->assertSessionHasNoErrors();
    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.store'), ['year' => 2027, 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])->assertSessionHasErrors('year');
    [$y26, $y27] = [DB::table('core_fiscal_years')->where('year', 2026)->value('id'), DB::table('core_fiscal_years')->where('year', 2027)->value('id')];

    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.status', $y26), ['status' => 'closed'])->assertSessionHasErrors('status');
    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.status', $y26), ['status' => 'running'])->assertSessionHasNoErrors();
    $this->actingAs($this->walidata)->post(route('admin.master-data.fiscal-years.status', $y27), ['status' => 'running'])->assertSessionHasNoErrors();

    expect(DB::table('core_fiscal_years')->where('year', 2026)->value('status'))->toBe('closed')
        ->and(DB::table('core_fiscal_years')->where('status', 'running')->value('year'))->toBe(2027);

    $auditor = userWithRole('auditor');
    $this->actingAs($auditor)->get(route('admin.master-data.fiscal-years'))->assertOk();
    $this->actingAs($auditor)->post(route('admin.master-data.fiscal-years.store'), ['year' => 2028, 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31'])->assertForbidden();
});

describe('orang & NIK', function (): void {
    test('NIK disimpan sebagai HMAC + terenkripsi, tidak pernah plaintext di DB, audit, atau event', function (): void {
        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.store'), [
            'full_name' => 'Siti Aminah', 'nik' => '6472 0145 0190 0003', 'phone' => '0812 3456 7890',
        ])->assertSessionHasNoErrors();

        $row = DB::table('core_persons')->first();
        expect($row->nik_hash)->toBe(app(PiiCipher::class)->hash('6472014501900003'))
            ->and($row->nik_enc)->not->toContain('6472014501900003')
            ->and(app(PiiCipher::class)->decrypt($row->nik_enc))->toBe('6472014501900003')
            ->and($row->nik_last4)->toBe('0003')
            ->and(json_encode(DB::table('audit_logs')->where('object_id', $row->id)->get()))->not->toContain('6472014501900003')->not->toContain('Siti')->not->toContain('0812')
            ->and(json_encode(DB::table('outbox_events')->where('aggregate_id', $row->id)->get()))->not->toContain('6472014501900003');

        // Pencarian NIK lengkap lewat hash; NIK tidak dipantulkan kembali ke halaman.
        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.search'), ['q' => '6472014501900003'])
            ->assertInertia(fn (Assert $p) => $p->has('persons', 1)->where('q', '')->where('searched_nik', true))
            ->assertDontSee('6472014501900003');
        $this->actingAs($this->walidata)->get(route('admin.master-data.persons.show', $row->id))->assertDontSee('6472014501900003');
    });

    test('NIK tidak sah atau ganda ditolak', function (): void {
        $store = fn (string $nik, string $name = 'A') => $this->actingAs($this->walidata)->post(route('admin.master-data.persons.store'), ['full_name' => $name, 'nik' => $nik]);

        $store('6472019901900003')->assertSessionHasErrors('nik');   // bulan 90
        $store('123')->assertSessionHasErrors('nik');
        $store('6472014501900003')->assertSessionHasNoErrors();
        $store('6472014501900003', 'B')->assertSessionHasErrors('nik');
        expect(DB::table('core_persons')->count())->toBe(1);
    });

    test('membuka NIK: butuh permission, konfirmasi kata sandi, alasan, dan tercatat pii.revealed', function (): void {
        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.store'), ['full_name' => 'Budi', 'nik' => '6472011201850001']);
        $id = (string) DB::table('core_persons')->value('id');
        $url = route('admin.master-data.persons.reveal', $id);

        $this->actingAs($this->walidata)->post($url, ['reason' => 'Verifikasi data bantuan sosial.'])->assertRedirect(route('password.confirm'));

        // Admin platform bisa melihat master data tetapi tidak bisa membuka NIK.
        $this->actingAs(userWithRole('platform_admin'))->withSession(['auth.password_confirmed_at' => time()])->post($url, ['reason' => 'Verifikasi data bantuan sosial.'])->assertForbidden();

        $this->actingAs($this->walidata)->withSession(['auth.password_confirmed_at' => time()])->post($url, ['reason' => 'pendek'])->assertSessionHasErrors('reason');
        $this->actingAs($this->walidata)->withSession(['auth.password_confirmed_at' => time()])->post($url, ['reason' => 'Verifikasi data bantuan sosial.'])
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('revealed_nik', '6472011201850001'));

        $audit = DB::table('audit_logs')->where('action', 'pii.revealed')->first();
        expect($audit->actor_id)->toBe($this->walidata->id)->and($audit->object_id)->toBe($id)
            ->and((string) $audit->context)->toContain('Verifikasi data bantuan sosial.')->not->toContain('6472011201850001');
    });

    test('penugasan pegawai: tambah, NIP unik, akhiri', function (): void {
        $dinkes = createOrganization('dinkes');
        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.store'), ['full_name' => 'Rina']);
        $id = (string) DB::table('core_persons')->value('id');
        $add = fn (?string $nip) => $this->actingAs($this->walidata)->post(route('admin.master-data.persons.employments.store', $id), [
            'org_id' => $dinkes->id, 'nip' => $nip, 'position' => 'Analis Kebijakan', 'valid_from' => '2026-01-02',
        ]);

        $add('199001012015032001')->assertSessionHasNoErrors();
        $add('199001012015032001')->assertSessionHasErrors('nip');
        $employee = (string) DB::table('core_employees')->value('id');
        expect(DB::table('objects')->where('id', $employee)->value('owner_org_id'))->toBe($dinkes->id);

        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.employments.end', ['person' => $id, 'employee' => $employee]), ['valid_to' => '2025-01-01'])
            ->assertSessionHasErrors('valid_to');
        $this->actingAs($this->walidata)->post(route('admin.master-data.persons.employments.end', ['person' => $id, 'employee' => $employee]), ['valid_to' => '2026-06-30'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->walidata)->get(route('admin.master-data.persons.show', $id))
            ->assertInertia(fn (Assert $p) => $p->where('person.employments.0.valid_to', '2026-06-30'));
    });
});

test('Core wilayah & orang dapat dirujuk aplikasi hanya setelah consumer disetujui; relasi ke orang wajib berklasifikasi pribadi', function (): void {
    importSample();
    $monev = createApplication('monev');
    $dinkes = createOrganization('dinkes');
    $lokasi = createEntity($monev, 'kegiatan', titleTemplate: '{nama}');
    addField($lokasi, 'nama', 'string', required: true);

    expect(fn () => addField($lokasi, 'desa', 'relationship', ['target_entity_id' => coreEntityId('region'), 'cardinality' => 'many_to_one']))
        ->toThrow(ValidationException::class, 'Walidata');

    approveConsumer($monev, coreEntityId('region'));
    approveConsumer($monev, coreEntityId('person'));
    addField($lokasi, 'desa', 'relationship', ['target_entity_id' => coreEntityId('region'), 'cardinality' => 'many_to_one']);
    expect(fn () => addField($lokasi, 'penanggung_jawab', 'relationship', ['target_entity_id' => coreEntityId('person'), 'cardinality' => 'many_to_one']))
        ->toThrow(ValidationException::class, 'data pribadi');
    addField($lokasi, 'penanggung_jawab', 'relationship', ['target_entity_id' => coreEntityId('person'), 'cardinality' => 'many_to_one'], classification: DataClassification::Personal);
    app(ReviewDraftPrivacy::class)->execute($lokasi->refresh(), userWithRole('dpo'));
    publishEntity($lokasi);

    $operator = userWithAppRole($monev, 'operator', $dinkes);
    $this->actingAs($operator)->getJson(route('runtime.lookup', ['app' => 'monev', 'entity' => 'kegiatan', 'field' => 'desa', 'q' => 'handil']))
        ->assertJsonPath('options.0.label', 'Handil Bakti (64.72.01.1002)');
});
