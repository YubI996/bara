<?php

declare(strict_types=1);

use App\Modules\Metadata\Actions\CreateDraft;
use App\Modules\Metadata\Actions\SaveDraftField;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\Field;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->dinkes = createOrganization('dinkes');
    $this->monev = createApplication('monev');
    $this->entity = createEntity($this->monev, 'kegiatan', titleTemplate: '{nama}');
    addField($this->entity, 'nama', 'string', required: true);
    addField($this->entity, 'jumlah', 'integer');
    addField($this->entity, 'status', 'enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'selesai', 'label' => 'Selesai']]]);
    addField($this->entity, 'jenis', 'enum', ['options' => [['value' => 'fisik', 'label' => 'Fisik'], ['value' => 'nonfisik', 'label' => 'Non-fisik']]]);
    publishEntity($this->entity);
    $this->operator = userWithAppRole($this->monev, 'operator', $this->dinkes);
});

function changeDraftField(Entity $entity, string $code, string $type, array $config): void
{
    $field = Field::query()->where('entity_version_id', $entity->refresh()->draft_version_id)->where('code', $code)->firstOrFail();
    app(SaveDraftField::class)->execute($entity, new FieldInput(
        code: $field->code, label: $field->label, helpText: null, type: $type, required: false, unique: false,
        indexed: false, searchable: false, classification: $field->classification, config: $config,
    ), $field);
}

function recordData(string $title): array
{
    return json_decode((string) DB::table('records')->where('title', $title)->value('data'), true);
}

function keyOf(Entity $entity, string $code): string
{
    return (string) DB::table('fields')->where('entity_version_id', $entity->refresh()->published_version_id)->where('code', $code)->value('field_key');
}

test('REQ-002 perubahan tipe kompatibel dan field wajib baru benar-benar memigrasi data lama', function (): void {
    $this->actingAs($this->operator)->post(route('runtime.store', ['app' => 'monev', 'entity' => 'kegiatan']), [
        'owner_org_id' => $this->dinkes->id,
        'data' => ['nama' => 'Lama', 'jumlah' => '12', 'status' => 'selesai', 'jenis' => 'fisik'],
    ])->assertSessionHasNoErrors();

    app(CreateDraft::class)->execute($this->entity->refresh());
    changeDraftField($this->entity, 'jumlah', 'decimal', ['scale' => 2]);
    changeDraftField($this->entity, 'status', 'multi_enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'selesai', 'label' => 'Selesai']]]);
    changeDraftField($this->entity, 'jenis', 'string', ['max_length' => 50]);
    addField($this->entity, 'sumber', 'string', ['default' => 'APBD'], required: true);
    publishEntity($this->entity);

    $data = recordData('Lama');
    expect($data[keyOf($this->entity, 'jumlah')])->toBe('12')
        ->and($data[keyOf($this->entity, 'status')])->toBe(['selesai'])
        ->and($data[keyOf($this->entity, 'jenis')])->toBe('Fisik')
        ->and($data[keyOf($this->entity, 'sumber')])->toBe('APBD')
        ->and(DB::table('records')->where('title', 'Lama')->value('lock_version'))->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'records.migrated')->exists())->toBeTrue();

    // Form ubah memuat nilai lama sebagai pilihan multi_enum yang tetap terpilih.
    $id = (string) DB::table('records')->where('title', 'Lama')->value('id');
    $this->actingAs($this->operator)->get(route('runtime.edit', ['app' => 'monev', 'entity' => 'kegiatan', 'record' => $id]))
        ->assertInertia(fn (Assert $p) => $p->where('record.values.status', ['selesai']));
});

test('REQ-009 versi terbit tidak bisa dikembalikan ke draft atau dihapus', function (): void {
    $version = $this->entity->refresh()->published_version_id;

    expect(fn () => DB::table('entity_versions')->where('id', $version)->update(['status' => 'draft']))->toThrow(QueryException::class);
    expect(fn () => DB::table('entity_versions')->where('id', $version)->delete())->toThrow(QueryException::class);
});

test('REQ-004 TRUNCATE audit_logs dan partisinya ditolak', function (): void {
    expect(fn () => DB::statement('TRUNCATE audit_logs'))->toThrow(QueryException::class);
    expect(fn () => DB::statement('TRUNCATE audit_logs_default'))->toThrow(QueryException::class);
});
