<?php

declare(strict_types=1);

use App\Modules\Metadata\Actions\CreateDraft;
use App\Modules\Metadata\Actions\InspectDraft;
use App\Modules\Metadata\Actions\ReviewDraftPrivacy;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Models\Relationship;
use App\Shared\Data\DataClassification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function allTypesEntity(): Entity
{
    $app = createApplication('monev');
    $program = createEntity($app, 'program', titleTemplate: '{nama}');
    addField($program, 'nama', 'string', ['max_length' => 200], required: true);
    publishEntity($program);

    $entity = createEntity($app, 'kegiatan', titleTemplate: '{nama} ({tahun})');
    addField($entity, 'nama', 'string', ['max_length' => 200], required: true, indexed: true);
    addField($entity, 'uraian', 'text');
    addField($entity, 'catatan', 'rich_text');
    addField($entity, 'tahun', 'integer', ['min' => 2020, 'max' => 2100], required: true, indexed: true);
    addField($entity, 'volume', 'decimal', ['scale' => 3]);
    addField($entity, 'pagu', 'money');
    addField($entity, 'capaian', 'percentage');
    addField($entity, 'prioritas', 'boolean', ['default' => false]);
    addField($entity, 'mulai', 'date', ['min' => '2020-01-01']);
    addField($entity, 'diperbarui', 'datetime');
    addField($entity, 'status', 'enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'berjalan', 'label' => 'Berjalan']]]);
    addField($entity, 'sasaran', 'multi_enum', ['options' => [['value' => 'anak', 'label' => 'Anak'], ['value' => 'lansia', 'label' => 'Lansia']]]);
    addField($entity, 'program', 'relationship', ['target_entity_id' => $program->id, 'cardinality' => 'many_to_one']);
    addField($entity, 'lampiran', 'file', ['mimes' => ['pdf', 'jpg'], 'max_files' => 3]);
    addField($entity, 'lokasi', 'region', ['level_min' => 3]);

    return $entity;
}

test('publikasi menghasilkan compiled_schema, relasi, permission, audit, dan event', function (): void {
    $entity = allTypesEntity();
    $version = publishEntity($entity);
    $schema = $version->compiled_schema;

    expect($version->status)->toBe('published')
        ->and($schema['json_schema']['properties'])->toHaveCount(15)
        ->and($schema['json_schema']['required'])->toBe(['nama', 'tahun'])
        ->and($schema['json_schema']['additionalProperties'])->toBeFalse()
        ->and(array_column($schema['indexes'], 'sql_cast'))->toBe(['text', 'bigint'])
        ->and($schema['relationships'][0]['code'])->toBe('program')
        ->and($entity->refresh()->published_version_id)->toBe($version->id)
        ->and($entity->draft_version_id)->toBeNull();

    expect(Relationship::query()->where('source_entity_id', $entity->id)->where('is_active', true)->count())->toBe(1);

    foreach (['view', 'create', 'update', 'delete', 'export'] as $action) {
        expect(DB::table('permissions')->where('code', "monev.kegiatan.{$action}")->exists())->toBeTrue();
    }

    expect(DB::table('audit_logs')->where(['action' => 'metadata.publish', 'object_id' => $entity->id])->exists())->toBeTrue()
        ->and(DB::table('outbox_events')->where(['event_type' => 'metadata.published', 'aggregate_id' => $entity->id])->exists())->toBeTrue();
});

test('publikasi ulang tanpa perubahan ditolak', function (): void {
    $entity = allTypesEntity();
    publishEntity($entity);
    app(CreateDraft::class)->execute($entity->refresh());

    expect(fn () => publishEntity($entity))->toThrow(ValidationException::class, 'Tidak ada perubahan');
});

test('versi baru: field_key dipertahankan dan versi lama menjadi superseded', function (): void {
    $entity = allTypesEntity();
    $v1 = publishEntity($entity);
    app(CreateDraft::class)->execute($entity->refresh());
    addField($entity, 'keterangan', 'string');
    $v2 = publishEntity($entity);

    $keysV1 = array_column($v1->compiled_schema['fields'], 'field_key');
    $keysV2 = array_column($v2->compiled_schema['fields'], 'field_key');

    expect($v2->version)->toBe(2)
        ->and($v1->refresh()->status)->toBe('superseded')
        ->and(array_slice($keysV2, 0, count($keysV1)))->toBe($keysV1);
});

test('versi terbit immutable di level database', function (): void {
    $entity = allTypesEntity();
    $version = publishEntity($entity);

    expect(fn () => DB::transaction(fn () => DB::table('entity_versions')->where('id', $version->id)
        ->update(['compiled_schema' => json_encode(['diretas' => true])])))
        ->toThrow(QueryException::class, 'immutable');
});

test('field wajib baru tanpa default pada versi berikutnya ditolak', function (): void {
    $entity = allTypesEntity();
    publishEntity($entity);
    app(CreateDraft::class)->execute($entity->refresh());
    addField($entity, 'wajib_baru', 'string', required: true);

    expect(fn () => publishEntity($entity))->toThrow(ValidationException::class, 'butuh nilai default');
});

test('template judul harus menunjuk field yang ada', function (): void {
    $entity = createEntity(createApplication('monev'), 'kegiatan', titleTemplate: '{tidak_ada}');
    addField($entity, 'nama', 'string');

    expect(fn () => publishEntity($entity))->toThrow(ValidationException::class, 'Template judul');
});

test('entity tanpa field tidak bisa dipublikasikan', function (): void {
    $entity = createEntity(createApplication('monev'));

    expect(fn () => publishEntity($entity))->toThrow(ValidationException::class, 'minimal satu field');
});

test('relasi ke entity aplikasi lain yang tidak dibagikan ditolak', function (): void {
    $other = createEntity(createApplication('lain'), 'rahasia');
    addField($other, 'nama', 'string');
    publishEntity($other);

    $entity = createEntity(createApplication('monev'));

    expect(fn () => addField($entity, 'rahasia', 'relationship', ['target_entity_id' => $other->id, 'cardinality' => 'many_to_one']))
        ->toThrow(ValidationException::class);
});

test('relasi ke entity bersama (shared) dari aplikasi lain diizinkan', function (): void {
    $shared = createEntity(createApplication('bappeda'), 'program', shared: true);
    addField($shared, 'nama', 'string');
    publishEntity($shared);

    $entity = createEntity(createApplication('monev'));
    addField($entity, 'program', 'relationship', ['target_entity_id' => $shared->id, 'cardinality' => 'many_to_one']);

    expect(publishEntity($entity)->status)->toBe('published');
});

test('relasi ke Core.Organization (entity fisik bersama) diizinkan', function (): void {
    $orgEntityId = DB::table('entities')->where('code', 'organization')->value('id');
    DB::table('entities')->where('id', $orgEntityId)->update(['is_shared' => true]);

    $entity = createEntity(createApplication('monev'));
    addField($entity, 'opd', 'relationship', ['target_entity_id' => $orgEntityId, 'cardinality' => 'many_to_one']);

    expect(publishEntity($entity)->status)->toBe('published');
});

describe('gate Pejabat PDP', function (): void {
    beforeEach(function (): void {
        $this->entity = createEntity(createApplication('dukcapil'), 'penduduk');
        addField($this->entity, 'nama', 'string');
        addField($this->entity, 'nik', 'string', ['max_length' => 16, 'pattern' => '[0-9]{16}'], classification: DataClassification::PersonalSpecific);
    });

    test('field data pribadi baru menahan publikasi sampai disetujui DPO', function (): void {
        expect(fn () => publishEntity($this->entity))->toThrow(ValidationException::class, 'Pejabat PDP');

        app(ReviewDraftPrivacy::class)->execute($this->entity->refresh(), userWithRole('dpo'));

        expect(publishEntity($this->entity)->status)->toBe('published')
            ->and(DB::table('audit_logs')->where('action', 'metadata.privacy_review')->exists())->toBeTrue();
    });

    test('perubahan draft setelah disetujui membatalkan persetujuan', function (): void {
        app(ReviewDraftPrivacy::class)->execute($this->entity->refresh(), userWithRole('dpo'));
        addField($this->entity, 'catatan', 'text');

        expect(EntityVersion::query()->find($this->entity->refresh()->draft_version_id)->privacy_reviewed_at)->toBeNull()
            ->and(fn () => publishEntity($this->entity))->toThrow(ValidationException::class, 'Pejabat PDP');
    });

    test('administrator platform tidak memegang hak persetujuan PDP', function (): void {
        $admin = userWithRole('platform_admin');

        expect($admin->can('reviewPrivacy', $this->entity->refresh()))->toBeFalse()
            ->and(userWithRole('dpo')->can('reviewPrivacy', $this->entity))->toBeTrue();
    });

    test('label bernuansa data pribadi tanpa klasifikasi pribadi memunculkan peringatan', function (): void {
        addField($this->entity, 'alamat_rumah', 'string', label: 'Alamat rumah');
        $report = app(InspectDraft::class)
            ->execute($this->entity->refresh(), EntityVersion::query()->findOrFail($this->entity->draft_version_id));

        expect(implode(' ', $report->warnings))->toContain('Alamat rumah');
    });
});

test('sinkronisasi akses idempoten dan mencabut permission platform yang tidak lagi dimiliki role', function (): void {
    $adminRole = DB::table('roles')->where('code', 'platform_admin')->value('id');
    DB::table('role_permissions')->insert(['role_id' => $adminRole, 'permission_code' => 'platform.privacy.review']);

    $this->artisan('bara:sync-access')->assertSuccessful();
    $this->artisan('bara:sync-access')->assertSuccessful();

    expect(DB::table('role_permissions')->where(['role_id' => $adminRole, 'permission_code' => 'platform.privacy.review'])->exists())->toBeFalse()
        ->and(DB::table('roles')->where('code', 'dpo')->count())->toBe(1);
});
