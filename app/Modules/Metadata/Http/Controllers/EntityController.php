<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Controllers;

use App\Modules\Metadata\Actions\CreateDraft;
use App\Modules\Metadata\Actions\CreateEntity;
use App\Modules\Metadata\Actions\DiscardDraft;
use App\Modules\Metadata\Actions\InspectDraft;
use App\Modules\Metadata\Actions\PublishEntityVersion;
use App\Modules\Metadata\Actions\ReviewDraftPrivacy;
use App\Modules\Metadata\Actions\UpdateEntity;
use App\Modules\Metadata\Contracts\FieldDefinition;
use App\Modules\Metadata\Http\Presenters\MetadataPresenter;
use App\Modules\Metadata\Http\Requests\EntityRequest;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EntityController
{
    use Concerns;

    public function __construct(private MetadataPresenter $presenter) {}

    public function create(Application $application): Response
    {
        Gate::authorize('update', $application);

        return Inertia::render('admin/entities/create', [
            'application' => $this->presenter->application($application),
            'visibilities' => $this->visibilities(),
        ]);
    }

    public function store(EntityRequest $request, Application $application, CreateEntity $action): RedirectResponse
    {
        $entity = $action->execute($application, $request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Entity {$entity->name} dibuat sebagai draft versi 1. Tambahkan field."]);

        return to_route('admin.entities.show', $entity);
    }

    public function show(Request $request, Entity $entity, InspectDraft $inspect): Response
    {
        Gate::authorize('view', $entity);
        $user = $this->user($request);
        $draft = $entity->draftVersion;
        $published = $entity->publishedVersion;

        return Inertia::render('admin/entities/show', [
            'application' => $this->presenter->application($entity->application),
            'entity' => $this->presenter->entity($entity),
            'draft' => $draft === null ? null : [
                'id' => $draft->id,
                'version' => $draft->version,
                'fields' => $draft->fields()->get()->map(
                    fn (Field $f): array => $this->presenter->field($f->toDefinition(), $f->id),
                )->all(),
                'privacy_reviewed_at' => $draft->privacy_reviewed_at?->toIso8601String(),
                'report' => $inspect->execute($entity, $draft)->toArray(),
            ],
            'published' => $published === null ? null : [
                'id' => $published->id,
                'version' => $published->version,
                'published_at' => $published->published_at?->toIso8601String(),
                'fields' => array_map(fn (FieldDefinition $f): array => $this->presenter->field($f), $published->definitions()),
            ],
            'versions' => $entity->versions()->where('status', '!=', 'draft')->orderByDesc('version')
                ->get(['id', 'version', 'status', 'change_summary', 'published_at'])
                ->map(fn (EntityVersion $v): array => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'status' => $v->status,
                    'change_summary' => $v->change_summary,
                    'published_at' => $v->published_at?->toIso8601String(),
                ])->all(),
            'visibilities' => $this->visibilities(),
            'can' => [
                'update' => $user->can('update', $entity),
                'publish' => $user->can('publish', $entity),
                'review_privacy' => $user->can('reviewPrivacy', $entity),
            ],
        ]);
    }

    public function update(EntityRequest $request, Entity $entity, UpdateEntity $action): RedirectResponse
    {
        $action->execute($entity, $request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan entity disimpan.']);

        return to_route('admin.entities.show', $entity);
    }

    public function createDraft(Entity $entity, CreateDraft $action): RedirectResponse
    {
        Gate::authorize('update', $entity);
        $draft = $action->execute($entity);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Draft versi {$draft->version} dibuat."]);

        return to_route('admin.entities.show', $entity);
    }

    public function discardDraft(Entity $entity, DiscardDraft $action): RedirectResponse
    {
        Gate::authorize('update', $entity);
        $action->execute($entity);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft dibuang.']);

        return to_route('admin.entities.show', $entity);
    }

    public function reviewPrivacy(Request $request, Entity $entity, ReviewDraftPrivacy $action): RedirectResponse
    {
        Gate::authorize('reviewPrivacy', $entity);
        $action->execute($entity, $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Field data pribadi pada draft disetujui.']);

        return to_route('admin.entities.show', $entity);
    }

    public function publish(Request $request, Entity $entity, PublishEntityVersion $action): RedirectResponse
    {
        Gate::authorize('publish', $entity);
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $note = $request->filled('note') ? $request->string('note')->toString() : null;
        $version = $action->execute($entity, $this->user($request), $note);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Versi {$version->version} dipublikasikan."]);

        return to_route('admin.entities.show', $entity);
    }

    /** @return list<array{value: string, label: string}> */
    private function visibilities(): array
    {
        $options = [];
        foreach (MetadataPresenter::VISIBILITY_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
