<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceIdleTimeout;
use App\Modules\Data\Jobs\ScanUploadedFile;
use App\Modules\Data\Scanning\ClamdScanner;
use App\Modules\Data\Scanning\FileScanner;
use App\Modules\Data\Scanning\ScanResult;
use App\Modules\Metadata\Actions\CreateDraft;
use App\Modules\Metadata\Actions\ReviewDraftPrivacy;
use App\Shared\Data\DataClassification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->dishub = createOrganization('dishub');
    $this->monev = createApplication('monev');
    $this->entity = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama}');
    addField($this->entity, 'nama', 'string', ['max_length' => 100], required: true);
    addField($this->entity, 'kode_rahasia', 'string', ['max_length' => 30], classification: DataClassification::Restricted);
    publishEntity($this->entity);
    $this->entity->refresh();
});

function hardeningUrl(string $name, array $params = [], string $entity = 'kegiatan'): string
{
    return route($name, ['app' => 'monev', 'entity' => $entity, ...$params]);
}

function storeKegiatan(object $test, $user, array $data, string $owner)
{
    return $test->actingAs($user)->post(hardeningUrl('runtime.store'), ['owner_org_id' => $owner, 'data' => $data]);
}

describe('SEC-001 judul record', function (): void {
    test('template judul yang memakai field restricted ditolak saat publikasi', function (): void {
        app(CreateDraft::class)->execute($this->entity->refresh());
        DB::table('entities')->where('id', $this->entity->id)->update(['title_template' => '{nama} {kode_rahasia}']);

        $admin = userWithRole('platform_admin');
        $this->actingAs($admin)->post(route('admin.entities.publish', $this->entity))
            ->assertSessionHasErrors('publish');
    });

    test('judul fallback tidak memakai field restricted walau field itu yang pertama', function (): void {
        $arsip = createEntity($this->monev, 'arsip');
        addField($arsip, 'kode', 'string', classification: DataClassification::Restricted);
        addField($arsip, 'judul', 'string', required: true);
        publishEntity($arsip);

        DB::table('roles')->insert(['code' => 'arsiparis', 'application_id' => $this->monev->id, 'name' => 'Arsiparis', 'clearance' => 'restricted', 'is_system' => false]);
        $petugas = userWithAppRole($this->monev, 'operator', $this->dinkes, twoFactor: true);
        DB::table('role_assignments')->insert(['user_id' => $petugas->id, 'role_id' => DB::table('roles')->where('code', 'arsiparis')->value('id'), 'scope_org_id' => $this->dinkes->id]);

        $this->actingAs($petugas)->post(hardeningUrl('runtime.store', entity: 'arsip'), ['owner_org_id' => $this->dinkes->id, 'data' => ['kode' => 'RAHASIA-1', 'judul' => 'Surat Edaran']])
            ->assertSessionHasNoErrors();

        expect(DB::table('records')->value('title'))->toBe('Surat Edaran');
    });
});

describe('SEC-002/003 relasi', function (): void {
    beforeEach(function (): void {
        $this->realisasi = createEntity($this->monev, 'realisasi', titleTemplate: '{uraian}');
        addField($this->realisasi, 'uraian', 'string', required: true);
        addField($this->realisasi, 'kegiatan', 'relationship', ['target_entity_id' => $this->entity->id, 'cardinality' => 'many_to_one']);
        publishEntity($this->realisasi);
    });

    test('judul target relasi di luar cakupan pembaca diganti label netral', function (): void {
        $dinkes = userWithAppRole($this->monev, 'operator', $this->dinkes);
        storeKegiatan($this, $dinkes, ['nama' => 'Kegiatan Tertutup'], $this->dinkes->id)->assertSessionHasNoErrors();
        $target = (string) DB::table('records')->where('title', 'Kegiatan Tertutup')->value('id');
        DB::table('objects')->where('id', $target)->update(['visibility' => 'private']);

        $this->actingAs($dinkes)->post(hardeningUrl('runtime.store', entity: 'realisasi'), ['owner_org_id' => $this->dinkes->id, 'data' => ['uraian' => 'Anak', 'kegiatan' => $target]])
            ->assertSessionHasNoErrors();
        $source = (string) DB::table('records')->where('title', 'Anak')->value('id');

        // Operator Dishub membaca record sumber (visibilitas internal) tetapi bukan target (private).
        $dishub = userWithAppRole($this->monev, 'viewer', $this->dishub);
        $this->actingAs($dishub)->get(hardeningUrl('runtime.show', ['record' => $source], 'realisasi'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('record.values.kegiatan.0.label', '(di luar kewenangan Anda)'))
            ->assertDontSee('Kegiatan Tertutup');

        $this->actingAs($dinkes)->get(hardeningUrl('runtime.show', ['record' => $source], 'realisasi'))
            ->assertInertia(fn (Assert $p) => $p->where('record.values.kegiatan.0.label', 'Kegiatan Tertutup'));
    });

    test('relasi ke entity aplikasi lain tanpa permission view: opsi kosong dan id ditolak', function (): void {
        $sakip = createApplication('sakip');
        $sasaran = createEntity($sakip, 'sasaran', shared: true, titleTemplate: '{nama}');
        addField($sasaran, 'nama', 'string', required: true);
        publishEntity($sasaran);
        $pemilikSakip = userWithAppRole($sakip, 'operator', $this->dinkes);
        $this->actingAs($pemilikSakip)->post(route('runtime.store', ['app' => 'sakip', 'entity' => 'sasaran']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'Sasaran Internal']])
            ->assertSessionHasNoErrors();
        $target = (string) DB::table('records')->where('title', 'Sasaran Internal')->value('id');

        $capaian = createEntity($this->monev, 'capaian', titleTemplate: '{uraian}');
        addField($capaian, 'uraian', 'string', required: true);
        addField($capaian, 'sasaran', 'relationship', ['target_entity_id' => $sasaran->id, 'cardinality' => 'many_to_one']);
        publishEntity($capaian);

        // Operator monev tanpa role di aplikasi sakip.
        $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
        $this->actingAs($operator)->get(hardeningUrl('runtime.create', entity: 'capaian'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('fields.1.options', []));
        $this->actingAs($operator)->post(hardeningUrl('runtime.store', entity: 'capaian'), ['owner_org_id' => $this->dinkes->id, 'data' => ['uraian' => 'X', 'sasaran' => $target]])
            ->assertSessionHasErrors('data.sasaran');
    });
});

describe('SEC-004 clearance', function (): void {
    beforeEach(function (): void {
        app(CreateDraft::class)->execute($this->entity->refresh());
        addField($this->entity, 'nik', 'string', ['max_length' => 16], classification: DataClassification::PersonalSpecific);
        app(ReviewDraftPrivacy::class)->execute($this->entity->refresh(), userWithRole('dpo'));
        publishEntity($this->entity);

        DB::table('roles')->insert(['code' => 'petugas_pdp', 'application_id' => $this->monev->id, 'name' => 'Petugas data pribadi', 'clearance' => 'personal_specific', 'is_system' => false]);
        $this->petugas = userWithAppRole($this->monev, 'operator', $this->dinkes, twoFactor: true);
        DB::table('role_assignments')->insert(['user_id' => $this->petugas->id, 'role_id' => DB::table('roles')->where('code', 'petugas_pdp')->value('id'), 'scope_org_id' => $this->dinkes->id]);
    });

    test('role platform dpo tidak memberi clearance data runtime', function (): void {
        storeKegiatan($this, $this->petugas, ['nama' => 'Warga', 'nik' => '3201010101010001'], $this->dinkes->id)->assertSessionHasNoErrors();
        $id = (string) DB::table('records')->where('title', 'Warga')->value('id');

        $viewerDpo = userWithAppRole($this->monev, 'viewer', $this->dinkes, twoFactor: true);
        DB::table('role_assignments')->insert(['user_id' => $viewerDpo->id, 'role_id' => DB::table('roles')->where('code', 'dpo')->whereNull('application_id')->value('id'), 'scope_org_id' => rootOrganization()->id]);

        $this->actingAs($viewerDpo)->get(hardeningUrl('runtime.show', ['record' => $id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->missing('record.values.nik'));
    });

    test('clearance tinggi tidak berlaku untuk record unit lain yang terbaca lewat visibilitas', function (): void {
        $dishubPetugas = userWithAppRole($this->monev, 'operator', $this->dishub, twoFactor: true);
        DB::table('role_assignments')->insert(['user_id' => $dishubPetugas->id, 'role_id' => DB::table('roles')->where('code', 'petugas_pdp')->value('id'), 'scope_org_id' => $this->dishub->id]);

        storeKegiatan($this, $this->petugas, ['nama' => 'Warga Dinkes', 'nik' => '3201010101010002', 'kode_rahasia' => 'R-9'], $this->dinkes->id)->assertSessionHasNoErrors();
        $id = (string) DB::table('records')->where('title', 'Warga Dinkes')->value('id');

        $this->actingAs($this->petugas)->get(hardeningUrl('runtime.show', ['record' => $id]))
            ->assertInertia(fn (Assert $p) => $p->where('record.values.nik', '3201010101010002'));
        $this->actingAs($dishubPetugas)->get(hardeningUrl('runtime.show', ['record' => $id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->missing('record.values.nik')->missing('record.values.kode_rahasia'));
        $this->actingAs($dishubPetugas)->get(hardeningUrl('runtime.index'))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->missing('rows.0.values.kode_rahasia'));
    });
});

describe('SEC-005/006 sesi', function (): void {
    test('app_admin tanpa 2FA diarahkan ke pengaturan keamanan; operator tidak', function (): void {
        $admin = userWithAppRole($this->monev, 'app_admin', $this->dinkes);
        $this->actingAs($admin)->get(hardeningUrl('runtime.index'))->assertRedirect(route('security.edit'));

        $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
        $this->actingAs($operator)->get(hardeningUrl('runtime.index'))->assertOk();
    });

    test('idle 30 menit mengeluarkan pengguna berisiko tinggi; operator mengikuti SESSION_LIFETIME', function (): void {
        $admin = userWithRole('platform_admin');
        $this->actingAs($admin)->withSession([EnforceIdleTimeout::SESSION_KEY => time() - 31 * 60])
            ->get(route('admin.organizations.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
        $this->actingAs($operator)->withSession([EnforceIdleTimeout::SESSION_KEY => time() - 31 * 60])
            ->get(hardeningUrl('runtime.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('session.idle_minutes', config('session.lifetime')));
    });
});

test('REQ-D01 detail aplikasi dengan entity terbit dan draft terbuka tanpa lazy loading', function (): void {
    app(CreateDraft::class)->execute($this->entity->refresh());
    $admin = userWithRole('platform_admin');

    $this->actingAs($admin)->get(route('admin.applications.show', $this->monev))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('entities.0.published_version', 1)->where('entities.0.draft_version', 2));
});

describe('REQ-001 pemindaian lampiran', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        config(['bara.files.scanner' => 'clamav']);
        app(CreateDraft::class)->execute($this->entity->refresh());
        addField($this->entity, 'bukti', 'file', ['mimes' => ['pdf'], 'max_files' => 1, 'max_kb' => 100]);
        publishEntity($this->entity);
        $this->operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    });

    function uploadBukti(object $test): array
    {
        $test->actingAs($test->operator)->post(hardeningUrl('runtime.store'), [
            'owner_org_id' => $test->dinkes->id,
            'data' => ['nama' => 'Berkas'],
            'files' => ['bukti' => [UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf')]],
        ])->assertSessionHasNoErrors();

        return [(string) DB::table('records')->where('title', 'Berkas')->value('id'), DB::table('files')->first()];
    }

    test('berkas tertahan pending sampai dipindai bersih', function (): void {
        Queue::fake();
        [$record, $file] = uploadBukti($this);

        expect($file->scan_status)->toBe('pending');
        Queue::assertPushed(ScanUploadedFile::class, fn (ScanUploadedFile $job) => $job->fileId === $file->id);
        $this->actingAs($this->operator)->get(hardeningUrl('runtime.files.download', ['record' => $record, 'file' => $file->id]))->assertNotFound();

        app()->instance(FileScanner::class, new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::clean();
            }
        });
        app()->call([new ScanUploadedFile($file->id), 'handle']);

        expect(DB::table('files')->value('scan_status'))->toBe('clean');
        $this->actingAs($this->operator)->get(hardeningUrl('runtime.files.download', ['record' => $record, 'file' => $file->id]))->assertOk();
        expect(DB::table('audit_logs')->where('action', 'file.scanned')->exists())->toBeTrue();
    });

    test('berkas terinfeksi tidak bisa diunduh dan dihapus dari disk', function (): void {
        Queue::fake();
        [$record, $file] = uploadBukti($this);

        app()->instance(FileScanner::class, new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::infected('Eicar-Test-Signature');
            }
        });
        app()->call([new ScanUploadedFile($file->id), 'handle']);

        $row = DB::table('files')->first();
        expect($row->scan_status)->toBe('infected')->and($row->scan_detail)->toBe('Eicar-Test-Signature');
        Storage::disk('local')->assertMissing($file->path);
        expect(DB::table('outbox_events')->where('event_type', 'file.infected')->exists())->toBeTrue();
        $this->actingAs($this->operator)->get(hardeningUrl('runtime.files.download', ['record' => $record, 'file' => $file->id]))->assertNotFound();
    });

    test('jawaban clamd diurai dengan benar', function (): void {
        expect(ClamdScanner::parse('stream: OK')->status)->toBe('clean')
            ->and(ClamdScanner::parse('stream: Eicar-Signature FOUND')->detail)->toBe('Eicar-Signature')
            ->and(ClamdScanner::parse('INSTREAM size limit exceeded. ERROR')->status)->toBe('error')
            ->and((new ClamdScanner('tcp://127.0.0.1:1', 0.5))->scan(fopen('php://memory', 'r'))->status)->toBe('error');
    });
});
