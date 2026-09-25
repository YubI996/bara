<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Models\User;
use App\Modules\Organization\Data\CreateOrganizationData;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use App\Shared\Validation\Identifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $parentId = $this->string('parent_id')->toString();
        // Hanya query bila format UUID valid; selain itu biarkan rules() yang melaporkan.
        $parent = Str::isUuid($parentId) ? Organization::query()->find($parentId) : null;

        // Induk tidak ada: biarkan validasi yang melaporkan; induk ada: wajib punya hak kelola di induk.
        return $user instanceof User
            && ($parent === null
                ? $user->can('create', Organization::class)
                : $user->can('createUnder', [Organization::class, $parent]));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'uuid', Rule::exists('core_organizations', 'id')],
            'code' => ['required', 'string', new Identifier, Rule::unique('core_organizations', 'code')],
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'kind' => ['required', Rule::enum(OrganizationKind::class)->except([OrganizationKind::Pemda])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'parent_id' => 'unit induk',
            'code' => 'kode',
            'name' => 'nama',
            'short_name' => 'singkatan',
            'kind' => 'jenis unit',
        ];
    }

    public function toData(): CreateOrganizationData
    {
        return new CreateOrganizationData(
            parentId: $this->string('parent_id')->toString(),
            code: $this->string('code')->toString(),
            name: $this->string('name')->trim()->toString(),
            shortName: $this->filled('short_name') ? $this->string('short_name')->trim()->toString() : null,
            kind: $this->enum('kind', OrganizationKind::class) ?? OrganizationKind::Lainnya,
        );
    }
}
