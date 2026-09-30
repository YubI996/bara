<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\MasterData\Support\PersonData;
use Illuminate\Foundation\Http\FormRequest;

final class PersonRequest extends FormRequest
{
    public function authorize(AccessChecker $access): bool
    {
        $user = $this->user();

        return $user instanceof User && $access->hasAnywhere($user, PlatformPermission::MasterDataManage);
    }

    protected function prepareForValidation(): void
    {
        // NIK sering ditempel dengan spasi/titik dari dokumen; simpan digitnya saja.
        if (is_string($this->input('nik'))) {
            $this->merge(['nik' => preg_replace('/\D+/', '', $this->string('nik')->toString())]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'nik' => ['nullable', 'digits:16'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['full_name' => 'nama lengkap', 'nik' => 'NIK', 'birth_date' => 'tanggal lahir', 'email' => 'alamat email', 'phone' => 'nomor telepon'];
    }

    /** Edit: NIK kosong = tidak diubah (NIK lama tidak pernah dikirim ke form). */
    public function toData(): PersonData
    {
        $nik = $this->string('nik')->toString();
        $optional = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return new PersonData(
            fullName: $this->string('full_name')->squish()->toString(),
            // Kosong = tidak diubah (edit) / tidak ada (baru). Menghapus NIK tidak didukung dari form.
            nik: $nik === '' ? null : $nik,
            birthDate: $optional('birth_date'),
            email: $optional('email'),
            phone: $optional('phone'),
        );
    }
}
