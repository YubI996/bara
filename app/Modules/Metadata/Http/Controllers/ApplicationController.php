<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Access\Contracts\PlatformPermission;
use App\Modules\Metadata\Actions\CreateApplication;
use App\Modules\Metadata\Actions\UpdateApplication;
use App\Modules\Metadata\Http\Presenters\MetadataPresenter;
use App\Modules\Metadata\Http\Requests\ApplicationRequest;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Modules\Organization\Contracts\OrganizationSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApplicationController
{
    use Concerns;

    private const array STATUSES = [
        ['value' => 'draft', 'label' => 'Draf (belum dipakai operator)'],
        ['value' => 'active', 'label' => 'Aktif'],
        ['value' => 'archived', 'label' => 'Diarsipkan'],
    ];

    public function __construct(
        private AccessChecker $access,
        private OrganizationDirectory $organizations,
        private MetadataPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Application::class);
        $user = $this->user($request);

        $orgIds = [];
        foreach ([PlatformPermission::MetadataManage, PlatformPermission::MetadataPublish, PlatformPermission::PrivacyReview] as $permission) {
            foreach ($this->organizations->activeWithin($this->access->grants($user, $permission)) as $org) {
                $orgIds[$org->id] = true;
            }
        }

        $applications = Application::query()
            ->where('is_system', false)
            ->whereIn('owner_org_id', array_keys($orgIds))
            ->withCount('entities')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/applications/index', [
            'applications' => $applications->map(fn (Application $app): array => [
                ...$this->presenter->application($app),
                'entities_count' => $app->getAttribute('entities_count'),
            ])->all(),
            'can' => ['create' => $user->can('create', Application::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Application::class);

        return Inertia::render('admin/applications/create', [
            'owners' => $this->ownerOptions($this->user($request)),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(ApplicationRequest $request, CreateApplication $action): RedirectResponse
    {
        $application = $action->execute($request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Aplikasi {$application->name} dibuat. Tambahkan entity pertamanya."]);

        return to_route('admin.applications.show', $application);
    }

    public function show(Request $request, Application $application): Response
    {
        Gate::authorize('view', $application);
        $user = $this->user($request);

        return Inertia::render('admin/applications/show', [
            'application' => $this->presenter->application($application),
            'entities' => $application->entities()->orderBy('name')->get()->map(fn (Entity $entity): array => [
                ...$this->presenter->entity($entity),
                'published_version' => $entity->publishedVersion?->version,
                'draft_version' => $entity->draftVersion?->version,
            ])->all(),
            'can' => ['update' => $user->can('update', $application)],
        ]);
    }

    public function edit(Request $request, Application $application): Response
    {
        Gate::authorize('update', $application);

        return Inertia::render('admin/applications/edit', [
            'application' => $this->presenter->application($application),
            'owners' => $this->ownerOptions($this->user($request)),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(ApplicationRequest $request, Application $application, UpdateApplication $action): RedirectResponse
    {
        $application = $action->execute($application, $request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Aplikasi {$application->name} diperbarui."]);

        return to_route('admin.applications.show', $application);
    }

    /** @return list<array{value: string, label: string, depth: int}> */
    private function ownerOptions(User $user): array
    {
        return array_map(
            static fn (OrganizationSummary $org): array => ['value' => $org->id, 'label' => $org->name, 'depth' => $org->depth],
            $this->organizations->activeWithin($this->access->grants($user, PlatformPermission::MetadataManage)),
        );
    }
}
