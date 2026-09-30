<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->bappeda = createApplication('bappeda');
    $this->program = createEntity($this->bappeda, 'program', shared: true, titleTemplate: '{nama}');
    addField($this->program, 'nama', 'string', required: true);
    publishEntity($this->program);
    $this->monev = createApplication('monev');
    $this->admin = userWithRole('platform_admin');
    $this->walidata = userWithRole('data_steward');
});

function requestConsumer(object $test, string $entityId, string $reason = 'Kegiatan monev merujuk program Bappeda.')
{
    return $test->actingAs($test->admin)->post(route('admin.consumers.store', $test->monev), ['entity_id' => $entityId, 'reason' => $reason]);
}

test('alur lengkap: admin mengajukan, Walidata menyetujui, relasi bisa dipublikasikan', function (): void {
    $this->actingAs($this->admin)->get(route('admin.applications.show', $this->monev))
        ->assertInertia(fn (Assert $p) => $p->where('consumable', fn ($options) => collect($options)->pluck('value')->contains($this->program->id)));

    requestConsumer($this, $this->program->id)->assertSessionHasNoErrors()->assertRedirect(route('admin.applications.show', $this->monev));
    expect(DB::table('entity_consumers')->value('status'))->toBe('pending');

    // Admin platform tidak bisa menyetujui pengajuannya sendiri (separation of duty).
    $this->actingAs($this->admin)->get(route('admin.consumers.index'))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.consumers.decide', ['entity' => $this->program->id, 'application' => $this->monev->id]), ['decision' => 'approve'])->assertForbidden();

    $this->actingAs($this->walidata)->get(route('admin.consumers.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/consumers/index')->has('pending', 1));
    $this->actingAs($this->walidata)->post(route('admin.consumers.decide', ['entity' => $this->program->id, 'application' => $this->monev->id]), ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $kegiatan = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama}');
    addField($kegiatan, 'nama', 'string', required: true);
    addField($kegiatan, 'program', 'relationship', ['target_entity_id' => $this->program->id, 'cardinality' => 'many_to_one']);
    expect(publishEntity($kegiatan)->status)->toBe('published')
        ->and(DB::table('audit_logs')->where('action', 'metadata.consumer_approved')->exists())->toBeTrue()
        ->and(DB::table('outbox_events')->where('event_type', 'metadata.consumer_requested')->exists())->toBeTrue();
});

test('penolakan wajib beralasan; pengajuan ulang setelah ditolak diizinkan', function (): void {
    requestConsumer($this, $this->program->id);
    $url = route('admin.consumers.decide', ['entity' => $this->program->id, 'application' => $this->monev->id]);

    $this->actingAs($this->walidata)->post($url, ['decision' => 'reject'])->assertSessionHasErrors('note');
    $this->actingAs($this->walidata)->post($url, ['decision' => 'reject', 'note' => 'Gunakan data Bappeda versi final.'])->assertSessionHasNoErrors();
    expect(DB::table('entity_consumers')->value('status'))->toBe('rejected');

    requestConsumer($this, $this->program->id)->assertSessionHasNoErrors();
    expect(DB::table('entity_consumers')->value('status'))->toBe('pending');
    requestConsumer($this, $this->program->id)->assertSessionHasErrors('entity_id');
});

test('pengajuan ditolak untuk entity tidak dibagikan, milik sendiri, atau alasan terlalu pendek', function (): void {
    $private = createEntity($this->bappeda, 'anggaran');
    addField($private, 'nama', 'string');
    publishEntity($private);
    $own = createEntity($this->monev, 'milik_sendiri', shared: true);

    requestConsumer($this, $private->id)->assertSessionHasErrors('entity_id');
    requestConsumer($this, $own->id)->assertSessionHasErrors('entity_id');
    requestConsumer($this, $this->program->id, 'pendek')->assertSessionHasErrors('reason');
});

test('pencabutan: tautan lama tetap, lookup & tautan baru ditolak', function (): void {
    approveConsumer($this->monev, $this->program);
    $kegiatan = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama}');
    addField($kegiatan, 'nama', 'string', required: true);
    addField($kegiatan, 'program', 'relationship', ['target_entity_id' => $this->program->id, 'cardinality' => 'many_to_one']);
    publishEntity($kegiatan);

    $pemilik = userWithAppRole($this->bappeda, 'operator', $this->dinkes);
    $this->actingAs($pemilik)->post(route('runtime.store', ['app' => 'bappeda', 'entity' => 'program']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'Program Gizi']]);
    $program = (string) DB::table('records')->where('title', 'Program Gizi')->value('id');

    $operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
    $lookup = route('runtime.lookup', ['app' => 'monev', 'entity' => 'kegiatan', 'field' => 'program', 'q' => 'gizi']);
    $this->actingAs($operator)->getJson($lookup)->assertJsonCount(1, 'options');
    $this->actingAs($operator)->post(route('runtime.store', ['app' => 'monev', 'entity' => 'kegiatan']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'Lama', 'program' => $program]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->walidata)->post(route('admin.consumers.decide', ['entity' => $this->program->id, 'application' => $this->monev->id]), ['decision' => 'revoke', 'note' => 'Program dipindah ke aplikasi SAKIP.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($operator)->getJson($lookup)->assertJsonCount(0, 'options');
    $this->actingAs($operator)->post(route('runtime.store', ['app' => 'monev', 'entity' => 'kegiatan']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'Baru', 'program' => $program]])
        ->assertSessionHasErrors('data.program');
    expect(DB::table('record_links')->where('target_id', $program)->count())->toBe(1);
});

test('consumer tidak bisa mengubah master data / data milik aplikasi pemilik (403)', function (): void {
    approveConsumer($this->monev, $this->program);
    $kegiatan = createEntity($this->monev, 'kegiatan');
    addField($kegiatan, 'nama', 'string');
    publishEntity($kegiatan);
    $pemilik = userWithAppRole($this->bappeda, 'operator', $this->dinkes);
    $this->actingAs($pemilik)->post(route('runtime.store', ['app' => 'bappeda', 'entity' => 'program']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'Program Air']]);
    $program = (string) DB::table('records')->where('title', 'Program Air')->value('id');

    $consumer = userWithAppRole($this->monev, 'app_admin', $this->dinkes, twoFactor: true);
    $url = ['app' => 'bappeda', 'entity' => 'program', 'record' => $program];
    $this->actingAs($consumer)->put(route('runtime.update', $url), ['lock_version' => 0, 'data' => ['nama' => 'Diubah']])->assertForbidden();
    $this->actingAs($consumer)->delete(route('runtime.destroy', $url))->assertForbidden();
    $this->actingAs($consumer)->post(route('runtime.store', ['app' => 'bappeda', 'entity' => 'program']), ['owner_org_id' => $this->dinkes->id, 'data' => ['nama' => 'X']])->assertForbidden();
    // Metadata milik aplikasi lain juga tidak bisa diubah consumer.
    $this->actingAs($consumer)->put(route('admin.entities.update', $this->program), ['name' => 'Ubah'])->assertForbidden();
});
