<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function organizationPayload(array $overrides = []): array
{
    return [
        'parent_id' => rootOrganization()->id,
        'code' => 'dinkes',
        'name' => 'Dinas Kesehatan',
        'short_name' => 'Dinkes',
        'kind' => OrganizationKind::Dinas->value,
        ...$overrides,
    ];
}

describe('akses halaman', function (): void {
    test('tamu diarahkan ke login', function (): void {
        $this->get(route('admin.organizations.index'))->assertRedirect(route('login'));
    });

    test('admin tanpa 2FA diarahkan ke pengaturan keamanan', function (): void {
        $user = userWithRole('platform_admin', twoFactor: false);

        $this->actingAs($user)->get(route('admin.organizations.index'))
            ->assertRedirect(route('security.edit'));
    });

    test('user tanpa role mendapat 403', function (): void {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)->get(route('admin.organizations.index'))->assertForbidden();
    });

    test('admin melihat daftar organisasi dalam scope saja', function (): void {
        $dinkes = createOrganization('dinkes');
        createOrganization('dishub');
        $user = userWithRole('platform_admin', $dinkes);

        $this->actingAs($user)->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/organizations/index')
                ->has('organizations', 1)
                ->where('organizations.0.code', 'dinkes'));
    });

    test('pencarian memfilter berdasarkan nama dan aman dari wildcard', function (): void {
        createOrganization('dinkes');
        createOrganization('dishub');

        $this->actingAs(userWithRole('platform_admin'))
            ->get(route('admin.organizations.index', ['q' => 'dishub']))
            ->assertInertia(fn (Assert $page) => $page->has('organizations', 1));

        $this->actingAs(userWithRole('platform_admin'))
            ->get(route('admin.organizations.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('organizations', 0));
    });

    test('id bukan UUID menghasilkan 404, bukan error database', function (): void {
        $this->actingAs(userWithRole('platform_admin'))
            ->get('/admin/organizations/1%27%20OR%201=1/edit')
            ->assertNotFound();
    });
});

describe('tambah unit', function (): void {
    test('admin menambah unit; path, objek, audit, dan event tercatat', function (): void {
        $admin = userWithRole('platform_admin');

        $this->actingAs($admin)->post(route('admin.organizations.store'), organizationPayload())
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHasNoErrors();

        $org = Organization::query()->where('code', 'dinkes')->firstOrFail();

        expect($org->path)->toBe(rootOrganization()->path.'.dinkes')
            ->and($org->sector->value)->toBe('government')
            ->and(DB::table('objects')->where('id', $org->id)->value('owner_path'))->toBe($org->path)
            ->and(DB::table('audit_logs')->where(['action' => 'organization.create', 'object_id' => $org->id, 'actor_id' => $admin->id])->exists())->toBeTrue()
            ->and(DB::table('outbox_events')->where(['event_type' => 'organization.created', 'aggregate_id' => $org->id])->exists())->toBeTrue();
    });

    test('kode tidak valid ditolak', function (string $code): void {
        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.store'), organizationPayload(['code' => $code]))
            ->assertSessionHasErrors('code');
    })->with([
        'huruf besar' => 'Dinkes',
        'spasi' => 'din kes',
        'titik (pemisah ltree)' => 'din.kes',
        'injeksi' => "x'; DROP TABLE users;--",
        'kata kunci SQL' => 'select',
        'diawali angka' => '1dinkes',
    ]);

    test('unit induk bukan UUID ditolak dengan pesan validasi, bukan error 500', function (string $parentId): void {
        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.store'), organizationPayload(['parent_id' => $parentId]))
            ->assertSessionHasErrors('parent_id');
    })->with(['kosong' => '', 'teks' => 'bukan-uuid', 'injeksi' => "' OR 1=1 --"]);

    test('kode duplikat ditolak', function (): void {
        createOrganization('dinkes');

        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.store'), organizationPayload())
            ->assertSessionHasErrors('code');
    });

    test('admin OPD tidak bisa menambah unit di luar scope-nya', function (): void {
        $dinkes = createOrganization('dinkes');
        $dishub = createOrganization('dishub');

        $this->actingAs(userWithRole('platform_admin', $dinkes))
            ->post(route('admin.organizations.store'), organizationPayload(['parent_id' => $dishub->id, 'code' => 'bid_x']))
            ->assertForbidden();

        expect(Organization::query()->where('code', 'bid_x')->exists())->toBeFalse();
    });

    test('jenis Pemda tidak boleh dipakai untuk unit turunan', function (): void {
        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.store'), organizationPayload(['kind' => 'pemda']))
            ->assertSessionHasErrors('kind');
    });
});

describe('ubah dan pindah unit', function (): void {
    test('memindahkan unit menulis ulang path seluruh turunan dan objeknya', function (): void {
        $dinkes = createOrganization('dinkes');
        $bidang = createOrganization('bid_p2p', $dinkes, OrganizationKind::Bidang);
        $seksi = createOrganization('seksi_imun', $bidang, OrganizationKind::Seksi);
        $setda = createOrganization('setda', kind: OrganizationKind::Sekretariat);
        $root = rootOrganization()->path;

        $this->actingAs(userWithRole('platform_admin'))
            ->put(route('admin.organizations.update', $bidang), [
                'parent_id' => $setda->id,
                'name' => 'Bidang P2P',
                'kind' => OrganizationKind::Bidang->value,
            ])
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHasNoErrors();

        expect($bidang->refresh()->path)->toBe("{$root}.setda.bid_p2p")
            ->and($seksi->refresh()->path)->toBe("{$root}.setda.bid_p2p.seksi_imun")
            ->and(DB::table('objects')->where('id', $seksi->id)->value('owner_path'))->toBe("{$root}.setda.bid_p2p.seksi_imun")
            ->and(DB::table('outbox_events')->where('event_type', 'organization.restructured')->count())->toBe(1);
    });

    test('unit tidak bisa dipindah ke bawah turunannya sendiri', function (): void {
        $dinkes = createOrganization('dinkes');
        $bidang = createOrganization('bid_p2p', $dinkes, OrganizationKind::Bidang);

        $this->actingAs(userWithRole('platform_admin'))
            ->put(route('admin.organizations.update', $dinkes), [
                'parent_id' => $bidang->id,
                'name' => 'Dinas Kesehatan',
                'kind' => OrganizationKind::Dinas->value,
            ])
            ->assertSessionHasErrors('parent_id');

        expect($dinkes->refresh()->path)->toBe(rootOrganization()->path.'.dinkes');
    });

    test('admin OPD tidak bisa mengubah unit OPD lain (IDOR)', function (): void {
        $dinkes = createOrganization('dinkes');
        $dishub = createOrganization('dishub');
        $user = userWithRole('platform_admin', $dinkes);

        $this->actingAs($user)->get(route('admin.organizations.edit', $dishub))->assertForbidden();
        $this->actingAs($user)->put(route('admin.organizations.update', $dishub), [
            'parent_id' => rootOrganization()->id,
            'name' => 'Diretas',
            'kind' => OrganizationKind::Dinas->value,
        ])->assertForbidden();

        expect($dishub->refresh()->name)->toBe('Unit dishub');
    });

    test('memindahkan unit ke luar scope ditolak', function (): void {
        $dinkes = createOrganization('dinkes');
        $bidang = createOrganization('bid_p2p', $dinkes, OrganizationKind::Bidang);
        $dishub = createOrganization('dishub');

        $this->actingAs(userWithRole('platform_admin', $dinkes))
            ->put(route('admin.organizations.update', $bidang), [
                'parent_id' => $dishub->id,
                'name' => 'Bidang P2P',
                'kind' => OrganizationKind::Bidang->value,
            ])
            ->assertForbidden();
    });

    test('unit akar tidak bisa dipindahkan', function (): void {
        $dinkes = createOrganization('dinkes');

        $this->actingAs(userWithRole('platform_admin'))
            ->put(route('admin.organizations.update', rootOrganization()), [
                'parent_id' => $dinkes->id,
                'name' => 'Pemda',
                'kind' => 'pemda',
            ])
            ->assertSessionHasErrors('parent_id');
    });

    test('perubahan tercatat di audit sebagai diff', function (): void {
        $dinkes = createOrganization('dinkes');

        $this->actingAs(userWithRole('platform_admin'))
            ->put(route('admin.organizations.update', $dinkes), [
                'parent_id' => rootOrganization()->id,
                'name' => 'Dinas Kesehatan',
                'kind' => OrganizationKind::Dinas->value,
            ]);

        $changes = json_decode((string) DB::table('audit_logs')
            ->where(['action' => 'organization.update', 'object_id' => $dinkes->id])->value('changes'), true);

        expect($changes)->toBe(['name' => ['Unit dinkes', 'Dinas Kesehatan']]);
    });
});

describe('nonaktifkan unit', function (): void {
    test('unit tanpa turunan aktif dapat dinonaktifkan', function (): void {
        $dinkes = createOrganization('dinkes');

        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.deactivate', $dinkes))
            ->assertSessionHasNoErrors();

        expect($dinkes->refresh()->isActive())->toBeFalse()
            ->and(DB::table('audit_logs')->where('action', 'organization.deactivate')->exists())->toBeTrue();
    });

    test('unit dengan turunan aktif tidak dapat dinonaktifkan', function (): void {
        $dinkes = createOrganization('dinkes');
        createOrganization('bid_p2p', $dinkes, OrganizationKind::Bidang);

        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.deactivate', $dinkes))
            ->assertSessionHasErrors('organization');

        expect($dinkes->refresh()->isActive())->toBeTrue();
    });

    test('unit akar tidak dapat dinonaktifkan', function (): void {
        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.deactivate', rootOrganization()))
            ->assertForbidden();
    });

    test('unit nonaktif tidak bisa menjadi induk baru', function (): void {
        $dinkes = createOrganization('dinkes');
        $this->actingAs(userWithRole('platform_admin'))->post(route('admin.organizations.deactivate', $dinkes));

        $this->actingAs(userWithRole('platform_admin'))
            ->post(route('admin.organizations.store'), organizationPayload(['parent_id' => $dinkes->id, 'code' => 'bid_x']))
            ->assertSessionHasErrors('parent_id');
    });

    test('scope pada unit nonaktif tidak lagi memberi akses', function (): void {
        $dinkes = createOrganization('dinkes');
        $user = userWithRole('platform_admin', $dinkes);
        $this->actingAs(userWithRole('platform_admin'))->post(route('admin.organizations.deactivate', $dinkes));

        $this->actingAs($user)->get(route('admin.organizations.index'))->assertForbidden();
    });
});

test('login dan login gagal tercatat di audit tanpa menyimpan email yang dicoba', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah-total']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(DB::table('audit_logs')->where('action', 'auth.login_failed')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where(['action' => 'auth.login', 'actor_id' => $user->id])->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('context', 'like', '%'.$user->email.'%')->exists())->toBeFalse()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});
