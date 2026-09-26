<?php

declare(strict_types=1);

use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Field;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('admin membuat aplikasi, entity, field, lalu mempublikasikan lewat HTTP', function (): void {
    $admin = userWithRole('platform_admin');

    $this->actingAs($admin)->post(route('admin.applications.store'), [
        'code' => 'monev', 'name' => 'Monitoring Program', 'owner_org_id' => rootOrganization()->id, 'status' => 'active',
    ])->assertSessionHasNoErrors();
    $app = Application::query()->where('code', 'monev')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.entities.store', $app), [
        'code' => 'realisasi', 'name' => 'Realisasi', 'name_plural' => 'Realisasi', 'default_visibility' => 'internal',
        'title_template' => '{uraian}',
    ])->assertSessionHasNoErrors();
    $entity = $app->entities()->firstOrFail();

    $this->actingAs($admin)->post(route('admin.fields.store', $entity), [
        'code' => 'uraian', 'label' => 'Uraian', 'type' => 'string', 'classification' => 'internal',
        'is_required' => '1', 'config' => ['max_length' => '200'],
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.fields.store', $entity), [
        'code' => 'status', 'label' => 'Status', 'type' => 'enum', 'classification' => 'internal',
        'config' => ['options' => [['value' => 'draf', 'label' => 'Draf'], ['value' => 'final', 'label' => 'Final']]],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get(route('admin.entities.show', $entity))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/entities/show')
            ->has('draft.fields', 2)
            ->where('draft.report.can_publish', true));

    $this->actingAs($admin)->post(route('admin.entities.publish', $entity), ['note' => 'Rilis awal'])
        ->assertSessionHasNoErrors();

    expect($entity->refresh()->published_version_id)->not->toBeNull()
        ->and(DB::table('entity_versions')->where('id', $entity->published_version_id)->value('change_summary'))->toStartWith('Rilis awal');
});

test('config field tidak valid dikembalikan sebagai error form', function (): void {
    $entity = createEntity(createApplication('monev'));

    $this->actingAs(userWithRole('platform_admin'))->post(route('admin.fields.store', $entity), [
        'code' => 'select', 'label' => 'Pola', 'type' => 'string', 'classification' => 'internal',
        'config' => ['pattern' => '(a+)+'],
    ])->assertSessionHasErrors(['code', 'config.pattern']);
});

test('kode aplikasi yang dicadangkan dan injeksi ditolak', function (string $code): void {
    $this->actingAs(userWithRole('platform_admin'))->post(route('admin.applications.store'), [
        'code' => $code, 'name' => 'Uji', 'owner_org_id' => rootOrganization()->id, 'status' => 'draft',
    ])->assertSessionHasErrors('code');
})->with(['core', 'admin', "x'; DROP TABLE applications;--", 'Monev']);

test('admin OPD hanya melihat dan mengelola aplikasi milik unitnya (IDOR)', function (): void {
    $dinkes = createOrganization('dinkes');
    $dishub = createOrganization('dishub');
    createApplication('sehat', $dinkes);
    $appDishub = createApplication('angkot', $dishub);
    $entityDishub = createEntity($appDishub);
    $fieldDishub = addField($entityDishub, 'nama');
    $user = userWithRole('platform_admin', $dinkes);

    $this->actingAs($user)->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->has('applications', 1)->where('applications.0.code', 'sehat'));

    $this->actingAs($user)->get(route('admin.applications.show', $appDishub))->assertForbidden();
    $this->actingAs($user)->get(route('admin.entities.show', $entityDishub))->assertForbidden();
    $this->actingAs($user)->post(route('admin.fields.store', $entityDishub), [
        'code' => 'x', 'label' => 'X', 'type' => 'string', 'classification' => 'internal',
    ])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.fields.destroy', [$entityDishub, $fieldDishub]))->assertForbidden();
    $this->actingAs($user)->post(route('admin.entities.publish', $entityDishub))->assertForbidden();

    // Membuat aplikasi atas nama unit lain juga ditolak.
    $this->actingAs($user)->post(route('admin.applications.store'), [
        'code' => 'palsu', 'name' => 'Palsu', 'owner_org_id' => $dishub->id, 'status' => 'draft',
    ])->assertForbidden();
});

test('field milik entity lain tidak bisa diakses lewat URL entity ini', function (): void {
    $app = createApplication('monev');
    $a = createEntity($app, 'alfa');
    $b = createEntity($app, 'beta');
    $fieldB = addField($b, 'nama');

    $this->actingAs(userWithRole('platform_admin'))
        ->delete(route('admin.fields.destroy', [$a, $fieldB]))
        ->assertNotFound();

    expect(Field::query()->find($fieldB->id))->not->toBeNull();
});

test('urutan field bisa diubah dengan tombol naik/turun', function (): void {
    $entity = createEntity(createApplication('monev'));
    $first = addField($entity, 'satu');
    $second = addField($entity, 'dua');

    $this->actingAs(userWithRole('platform_admin'))
        ->post(route('admin.fields.move', [$entity, $second]), ['direction' => 'up'])
        ->assertRedirect();

    expect($second->refresh()->position)->toBe(1)->and($first->refresh()->position)->toBe(2);
});

test('auditor tanpa hak metadata tidak dapat membuka menu aplikasi', function (): void {
    $this->actingAs(userWithRole('auditor'))->get(route('admin.applications.index'))->assertForbidden();
});
