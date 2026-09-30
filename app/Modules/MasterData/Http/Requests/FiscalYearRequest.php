<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use Illuminate\Foundation\Http\FormRequest;

final class FiscalYearRequest extends FormRequest
{
    public function authorize(AccessChecker $access): bool
    {
        $user = $this->user();

        return $user instanceof User && $access->hasAnywhere($user, PlatformPermission::MasterDataManage);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['year' => 'tahun', 'starts_on' => 'tanggal mulai', 'ends_on' => 'tanggal selesai'];
    }
}
