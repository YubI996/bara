<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Models\User;
use App\Modules\Organization\Data\UpdateOrganizationData;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (! $user instanceof User || ! $user->can('update', $organization)) {
            return false;
        }

        // Memindahkan unit juga butuh hak kelola di induk tujuan.
        if ($this->filled('parent_id') && $this->input('parent_id') !== $organization->parent_id) {
            $targetId = $this->string('parent_id')->toString();
            $target = Str::isUuid($targetId) ? Organization::query()->find($targetId) : null;

            return $target === null || $user->can('createUnder', [Organization::class, $target]);
        }

        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $isRoot = $this->organization()->isRoot();

        return [
            'parent_id' => $isRoot
                ? ['prohibited']
                : ['required', 'uuid', Rule::exists('core_organizations', 'id')],
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'kind' => $isRoot
                ? ['required', Rule::in([OrganizationKind::Pemda->value])]
                : ['required', Rule::enum(OrganizationKind::class)->except([OrganizationKind::Pemda])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'parent_id' => 'unit induk',
            'name' => 'nama',
            'short_name' => 'singkatan',
            'kind' => 'jenis unit',
        ];
    }

    public function organization(): Organization
    {
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            throw new NotFoundHttpException;
        }

        return $organization;
    }

    public function toData(): UpdateOrganizationData
    {
        return new UpdateOrganizationData(
            parentId: $this->filled('parent_id') ? $this->string('parent_id')->toString() : null,
            name: $this->string('name')->trim()->toString(),
            shortName: $this->filled('short_name') ? $this->string('short_name')->trim()->toString() : null,
            kind: $this->enum('kind', OrganizationKind::class) ?? OrganizationKind::Lainnya,
        );
    }
}
