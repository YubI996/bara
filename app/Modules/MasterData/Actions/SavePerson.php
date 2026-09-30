<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Data\Contracts\ObjectRegistry;
use App\Modules\Data\Contracts\Visibility;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\MasterData\Support\CoreEntities;
use App\Modules\MasterData\Support\PersonData;
use App\Modules\MasterData\Support\PiiCipher;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use Illuminate\Database\ConnectionInterface;

/**
 * Tambah/ubah orang (Core.Person). NIK disimpan sebagai HMAC + terenkripsi; audit dan event
 * hanya mencatat NAMA FIELD yang berubah, tidak pernah nilainya (docs/04 §9). Objek orang
 * ber-visibilitas private: hanya bisa dilihat lewat relasi yang disetujui.
 */
final readonly class SavePerson
{
    public function __construct(
        private ConnectionInterface $db,
        private ObjectRegistry $objects,
        private OrganizationDirectory $organizations,
        private CoreEntities $core,
        private PiiCipher $cipher,
        private AuditLogger $audit,
        private EventRecorder $events,
    ) {}

    public function execute(PersonData $data, ?string $personId = null): string
    {
        if ($data->nik !== null && $data->nik !== '' && ! PiiCipher::validNik($data->nik)) {
            throw MasterDataRuleViolation::on('nik', 'NIK harus 16 digit dengan kode wilayah dan tanggal lahir yang sah.');
        }

        return $this->db->transaction(function () use ($data, $personId): string {
            $values = [
                'full_name' => $data->fullName,
                'birth_date' => $data->birthDate,
                'email' => $data->email,
                'phone' => $data->phone,
                'updated_at' => now(),
            ];

            if ($data->nik !== null) {
                $hash = $data->nik === '' ? null : $this->cipher->hash($data->nik);
                $duplicate = $hash !== null && $this->db->table('core_persons')->where('nik_hash', $hash)
                    ->when($personId !== null, fn ($q) => $q->where('id', '<>', $personId))->exists();

                if ($duplicate) {
                    throw MasterDataRuleViolation::on('nik', 'NIK ini sudah terdaftar untuk orang lain. Cari dengan NIK untuk membuka datanya.');
                }

                $values += [
                    'nik_hash' => $hash,
                    'nik_enc' => $data->nik === '' ? null : $this->cipher->encrypt($data->nik),
                    'nik_last4' => $data->nik === '' ? null : substr($data->nik, -4),
                ];
            }

            if ($personId === null) {
                $root = $this->organizations->root();
                $personId = $this->objects->register($this->core->id('person'), $root->id, $root->path, Visibility::Private);
                $this->db->table('core_persons')->insert(['id' => $personId, ...$values, 'created_at' => now()]);
                $changed = array_keys(array_filter($values, fn ($v): bool => $v !== null));
                $action = 'created';
            } else {
                $before = $this->db->table('core_persons')->where('id', $personId)->lockForUpdate()->first();
                if (! is_object($before)) {
                    throw MasterDataRuleViolation::on('person', 'Data orang tidak ditemukan.');
                }
                $changed = [];
                foreach ($values as $column => $value) {
                    if ($column !== 'updated_at' && ($before->{$column} ?? null) != $value) {
                        $changed[] = $column;
                    }
                }
                $this->db->table('core_persons')->where('id', $personId)->update($values);
                $action = 'updated';
            }

            $fields = array_values(array_diff($changed, ['updated_at', 'nik_enc', 'nik_last4']));
            // Nilai tidak dicatat: hanya penanda field yang berubah.
            $this->audit->log("masterdata.person_{$action}", $personId, 'core.person', array_fill_keys($fields, ['[disamarkan]', '[disamarkan]']));
            $this->events->record("masterdata.person_{$action}", 'core.person', $personId, ['fields' => $fields]);

            return $personId;
        });
    }
}
