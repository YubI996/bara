<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\MasterData\Actions\AddEmployment;
use App\Modules\MasterData\Actions\CreateFiscalYear;
use App\Modules\MasterData\Actions\ImportRegions;
use App\Modules\MasterData\Actions\SavePerson;
use App\Modules\MasterData\Support\PersonData;
use Illuminate\Support\Facades\DB;

/**
 * Fixture E2E M4: admin e2e juga Walidata (agar semua halaman master data terjangkau), contoh
 * wilayah, satu orang ber-NIK sintetis, satu tahun anggaran, dan satu pengajuan consumer.
 */
final class E2eMasterDataFixture
{
    public function __invoke(User $admin, string $rootId, string $dinkesId): void
    {
        DB::table('role_assignments')->insert([
            'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('code', 'data_steward')->whereNull('application_id')->value('id'),
            'scope_org_id' => $rootId,
        ]);

        app(ImportRegions::class)->execute([
            '64' => 'Kalimantan Timur',
            '64.72' => 'Kota Samarinda',
            '64.72.01' => 'Palaran',
            '64.72.01.1001' => 'Rawa Makmur',
            '64.72.01.1002' => 'Handil Bakti',
        ], 'Kepmendagri 300.2.2-2430 Tahun 2025');

        app(CreateFiscalYear::class)->execute(2026, '2026-01-01', '2026-12-31');

        // NIK sintetis (bukan milik orang nyata): kode wilayah 647201, tgl 01-01-90, urut 0001.
        $person = app(SavePerson::class)->execute(new PersonData('Contoh Pegawai', '6472010101900001', '1990-01-01', null, null));
        app(AddEmployment::class)->execute($person, $dinkesId, null, 'Analis Data', null, '2026-01-02');

        $appId = DB::table('applications')->where('code', 'uji')->value('id');
        DB::table('entity_consumers')->insert([
            'entity_id' => DB::table('entities')->where('code', 'region')->value('id'),
            'application_id' => $appId,
            'access' => 'reference',
            'status' => 'pending',
            'reason' => 'Lokasi kegiatan merujuk kode wilayah Kemendagri.',
            'requested_by' => $admin->id,
        ]);
    }
}
