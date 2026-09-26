<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Organization\Actions\CreateOrganization;
use App\Modules\Organization\Data\CreateOrganizationData;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Data untuk tes end-to-end (Playwright). DILARANG dijalankan di produksi.
 * Admin memakai kode pemulihan 2FA yang diketahui agar tes bisa login tanpa TOTP.
 */
final class E2eSeeder extends Seeder
{
    public const string ADMIN_EMAIL = 'admin@e2e.test';

    public const string ADMIN_PASSWORD = 'e2e-password-aman';

    /** @var list<string> */
    public const array RECOVERY_CODES = [
        'e2e-recovery-1', 'e2e-recovery-2', 'e2e-recovery-3', 'e2e-recovery-4', 'e2e-recovery-5', 'e2e-recovery-6',
        'e2e-recovery-7', 'e2e-recovery-8', 'e2e-recovery-9', 'e2e-recovery-10', 'e2e-recovery-11', 'e2e-recovery-12',
    ];

    public function run(CreateOrganization $createOrganization): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eSeeder tidak boleh dijalankan di produksi.');
        }

        config(['bara.admin.email' => self::ADMIN_EMAIL, 'bara.admin.password' => self::ADMIN_PASSWORD]);
        $this->call(PlatformBootstrapSeeder::class);

        User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail()->forceFill([
            'password' => Hash::make(self::ADMIN_PASSWORD),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(self::RECOVERY_CODES, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $root = Organization::query()->whereNull('parent_id')->firstOrFail();
        $dinkes = $createOrganization->execute(new CreateOrganizationData($root->id, 'dinkes', 'Dinas Kesehatan', 'Dinkes', OrganizationKind::Dinas));
        $createOrganization->execute(new CreateOrganizationData($dinkes->id, 'bid_p2p', 'Bidang Pencegahan dan Pengendalian Penyakit', 'P2P', OrganizationKind::Bidang));
        $createOrganization->execute(new CreateOrganizationData($root->id, 'setda', 'Sekretariat Daerah', 'Setda', OrganizationKind::Sekretariat));

        (new E2eRuntimeFixture)(User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail(), $root->id);
    }
}
