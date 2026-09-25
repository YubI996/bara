<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Organization\Actions\CreateOrganization;
use App\Modules\Organization\Actions\DeactivateOrganization;
use App\Modules\Organization\Actions\UpdateOrganization;
use App\Modules\Organization\Enums\OrganizationKind;
use App\Modules\Organization\Http\Requests\StoreOrganizationRequest;
use App\Modules\Organization\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organization\Infrastructure\OrganizationQuery;
use App\Modules\Organization\Models\Organization;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationController
{
    public function __construct(
        private AccessChecker $access,
        private OrganizationQuery $organizations,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Organization::class);
        $user = $this->user($request);
        $search = $request->string('q')->trim()->limit(100, '')->toString();

        $rows = $this->organizations->within(
            $this->access->grants($user, PlatformPermission::OrganizationView),
            $search,
        );

        return Inertia::render('admin/organizations/index', [
            'organizations' => $rows->map(fn (Organization $org): array => [
                ...$this->present($org),
                'can_update' => $user->can('update', $org),
            ])->all(),
            'filters' => ['q' => $search],
            'can' => ['create' => $user->can('create', Organization::class)],
            'limit' => OrganizationQuery::MAX_ROWS,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Organization::class);

        return Inertia::render('admin/organizations/create', [
            'parents' => $this->parentOptions($this->user($request)),
            'kinds' => OrganizationKind::options(),
            'defaultParentId' => $request->string('parent')->toString() ?: null,
        ]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $action): RedirectResponse
    {
        $organization = $action->execute($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Unit {$organization->name} berhasil ditambahkan."]);

        return to_route('admin.organizations.index');
    }

    public function edit(Request $request, Organization $organization): Response
    {
        Gate::authorize('update', $organization);
        $user = $this->user($request);

        return Inertia::render('admin/organizations/edit', [
            'organization' => $this->present($organization),
            'parents' => $organization->isRoot() ? [] : $this->parentOptions($user, exclude: $organization),
            'kinds' => $organization->isRoot()
                ? [['value' => OrganizationKind::Pemda->value, 'label' => OrganizationKind::Pemda->label()]]
                : OrganizationKind::options(),
            'can' => ['deactivate' => $organization->isActive() && $user->can('deactivate', $organization)],
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization, UpdateOrganization $action): RedirectResponse
    {
        $organization = $action->execute($organization, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Unit {$organization->name} berhasil diperbarui."]);

        return to_route('admin.organizations.index');
    }

    public function deactivate(Organization $organization, DeactivateOrganization $action): RedirectResponse
    {
        Gate::authorize('deactivate', $organization);
        $organization = $action->execute($organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Unit {$organization->name} dinonaktifkan."]);

        return to_route('admin.organizations.index');
    }

    /** @return array<string, mixed> */
    private function present(Organization $org): array
    {
        return [
            'id' => $org->id,
            'parent_id' => $org->parent_id,
            'code' => $org->code,
            'name' => $org->name,
            'short_name' => $org->short_name,
            'kind' => $org->kind->value,
            'kind_label' => $org->kind->label(),
            'path' => $org->path,
            'depth' => $org->depth(),
            'is_active' => $org->isActive(),
            'is_root' => $org->isRoot(),
            'valid_to' => $org->valid_to?->toDateString(),
        ];
    }

    /** @return list<array{value: string, label: string, depth: int}> */
    private function parentOptions(User $user, ?Organization $exclude = null): array
    {
        $rows = $this->organizations->within(
            $this->access->grants($user, PlatformPermission::OrganizationManage),
            activeOnly: true,
        );

        return array_values($rows
            ->reject(fn (Organization $org): bool => $exclude !== null
                && ($org->id === $exclude->id || str_starts_with($org->path, $exclude->path.'.')))
            ->map(fn (Organization $org): array => [
                'value' => $org->id,
                'label' => $org->name,
                'depth' => $org->depth(),
            ])
            ->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
