<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Metadata\Actions\CreateApplication;
use App\Modules\Metadata\Actions\CreateEntity;
use App\Modules\Metadata\Actions\PublishEntityVersion;
use App\Modules\Metadata\Actions\SaveDraftField;
use App\Modules\Metadata\Data\ApplicationData;
use App\Modules\Metadata\Data\EntityData;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Entity;
use App\Shared\Data\DataClassification;
use Illuminate\Support\Facades\DB;

/**
 * Fixture E2E runtime: aplikasi `uji` dengan entity `semua_tipe` berisi setiap tipe field,
 * dan admin E2E sebagai app_admin di unit akar (docs/12 M2: form semua tipe lolos axe).
 */
final class E2eRuntimeFixture
{
    public function __invoke(User $admin, string $rootOrgId): void
    {
        $app = app(CreateApplication::class)->execute(new ApplicationData('Aplikasi Uji', null, $rootOrgId, 'active', 'uji'));

        $target = app(CreateEntity::class)->execute($app, new EntityData('Program', 'Program', null, 'internal', false, '{nama}', 'program'));
        $this->field($target, 'nama', 'Nama program', 'string', [], true);
        app(PublishEntityVersion::class)->execute($target->refresh(), $admin);

        $entity = app(CreateEntity::class)->execute($app, new EntityData('Contoh', 'Contoh semua tipe', null, 'internal', false, '{judul}', 'semua_tipe'));
        $this->field($entity, 'judul', 'Judul', 'string', ['max_length' => 100], true);
        $this->field($entity, 'uraian', 'Uraian', 'text');
        $this->field($entity, 'catatan', 'Catatan berformat', 'rich_text');
        $this->field($entity, 'jumlah', 'Jumlah peserta', 'integer', ['min' => 0]);
        $this->field($entity, 'volume', 'Volume', 'decimal', ['scale' => 2]);
        $this->field($entity, 'pagu', 'Pagu', 'money');
        $this->field($entity, 'capaian', 'Capaian', 'percentage');
        $this->field($entity, 'prioritas', 'Prioritas', 'boolean');
        $this->field($entity, 'mulai', 'Tanggal mulai', 'date');
        $this->field($entity, 'jadwal', 'Jadwal rapat', 'datetime');
        $this->field($entity, 'status', 'Status', 'enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'selesai', 'label' => 'Selesai']]]);
        $this->field($entity, 'kategori', 'Kategori', 'enum', ['options' => array_map(fn (int $i): array => ['value' => "k{$i}", 'label' => "Kategori {$i}"], range(1, 7))]);
        $this->field($entity, 'sasaran', 'Sasaran', 'multi_enum', ['options' => [['value' => 'anak', 'label' => 'Anak'], ['value' => 'lansia', 'label' => 'Lansia']]]);
        $this->field($entity, 'program', 'Program', 'relationship', ['target_entity_id' => $target->id, 'cardinality' => 'many_to_one']);
        $this->field($entity, 'lampiran', 'Lampiran', 'file', ['mimes' => ['pdf'], 'max_files' => 2]);
        $this->field($entity, 'lokasi', 'Lokasi', 'region');
        app(PublishEntityVersion::class)->execute($entity->refresh(), $admin);

        DB::table('role_assignments')->insert([
            'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('application_id', $app->id)->where('code', 'app_admin')->value('id'),
            'scope_org_id' => $rootOrgId,
        ]);
    }

    /** @param  array<string, mixed>  $config */
    private function field(Entity $entity, string $code, string $label, string $type, array $config = [], bool $required = false): void
    {
        app(SaveDraftField::class)->execute($entity->refresh(), new FieldInput(
            $code, $label, null, $type, $required, false, false, false, DataClassification::Internal, $config,
        ));
    }
}
