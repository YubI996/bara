<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Requests;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Metadata\Data\ApplicationData;
use App\Modules\Metadata\Models\Application;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Shared\Validation\Identifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApplicationRequest extends FormRequest
{
    /** Kode yang dipakai sistem sebagai segmen URL/permission. */
    public const array RESERVED_CODES = ['platform', 'admin', 'api', 'apps', 'core', 'collaboration', 'settings', 'system', 'auth'];

    public function authorize(): bool
    {
        $user = $this->user();
        $application = $this->application();

        if (! $user instanceof User) {
            return false;
        }

        if ($application !== null && ! $user->can('update', $application)) {
            return false;
        }

        if ($application === null && ! $user->can('create', Application::class)) {
            return false;
        }

        // Pemilik (baru) harus berada dalam scope pengelolaan user; pemilik tidak valid dilaporkan rules().
        $owner = app(OrganizationDirectory::class)->find($this->string('owner_org_id')->toString());

        return $owner === null
            || app(AccessChecker::class)->allows($user, PlatformPermission::MetadataManage, $owner->path);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'owner_org_id' => ['required', 'uuid', Rule::exists('core_organizations', 'id')->whereNull('valid_to')],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
        ];

        if ($this->application() === null) {
            $rules['code'] = ['required', 'string', new Identifier, Rule::notIn(self::RESERVED_CODES), Rule::unique('applications', 'code')];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'description' => 'deskripsi',
            'owner_org_id' => 'unit pemilik',
            'status' => 'status',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.not_in' => 'Kode ini dicadangkan sistem. Pakai kode lain.'];
    }

    public function application(): ?Application
    {
        $application = $this->route('application');

        return $application instanceof Application ? $application : null;
    }

    public function toData(): ApplicationData
    {
        return new ApplicationData(
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            ownerOrgId: $this->string('owner_org_id')->toString(),
            status: $this->string('status')->toString(),
            code: $this->application() === null ? $this->string('code')->toString() : null,
        );
    }
}
