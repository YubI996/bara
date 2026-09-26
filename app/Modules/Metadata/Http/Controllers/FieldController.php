<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Controllers;

use App\Modules\Metadata\Actions\MoveDraftField;
use App\Modules\Metadata\Actions\RemoveDraftField;
use App\Modules\Metadata\Actions\SaveDraftField;
use App\Modules\Metadata\Http\Presenters\MetadataPresenter;
use App\Modules\Metadata\Http\Requests\FieldRequest;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class FieldController
{
    public function __construct(private MetadataPresenter $presenter) {}

    public function create(Entity $entity): Response
    {
        Gate::authorize('update', $entity);
        $this->requireDraft($entity);

        return Inertia::render('admin/fields/form', [
            'application' => $this->presenter->application($entity->application),
            'entity' => $this->presenter->entity($entity),
            'field' => null,
            ...$this->presenter->fieldFormOptions($entity),
        ]);
    }

    public function store(FieldRequest $request, Entity $entity, SaveDraftField $action): RedirectResponse
    {
        $field = $action->execute($entity, $request->toInput());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Field {$field->label} ditambahkan ke draft."]);

        return to_route('admin.entities.show', $entity);
    }

    public function edit(Entity $entity, Field $field): Response
    {
        Gate::authorize('update', $entity);
        $this->requireDraftField($entity, $field);

        return Inertia::render('admin/fields/form', [
            'application' => $this->presenter->application($entity->application),
            'entity' => $this->presenter->entity($entity),
            'field' => $this->presenter->field($field->toDefinition(), $field->id),
            ...$this->presenter->fieldFormOptions($entity),
        ]);
    }

    public function update(FieldRequest $request, Entity $entity, Field $field, SaveDraftField $action): RedirectResponse
    {
        $this->requireDraftField($entity, $field);
        $field = $action->execute($entity, $request->toInput(), $field);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Field {$field->label} diperbarui."]);

        return to_route('admin.entities.show', $entity);
    }

    public function destroy(Entity $entity, Field $field, RemoveDraftField $action): RedirectResponse
    {
        Gate::authorize('update', $entity);
        $this->requireDraftField($entity, $field);
        $action->execute($entity, $field);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Field {$field->label} dihapus dari draft."]);

        return to_route('admin.entities.show', $entity);
    }

    public function move(Request $request, Entity $entity, Field $field, MoveDraftField $action): RedirectResponse
    {
        Gate::authorize('update', $entity);
        $this->requireDraftField($entity, $field);
        $request->validate(['direction' => ['required', 'in:up,down']]);
        $action->execute($entity, $field, $request->string('direction')->toString() === 'up' ? 'up' : 'down');

        return to_route('admin.entities.show', $entity);
    }

    private function requireDraft(Entity $entity): void
    {
        if ($entity->draft_version_id === null) {
            throw new NotFoundHttpException('Entity tidak punya draft.');
        }
    }

    /** Field harus milik draft entity ini (mencegah IDOR lintas entity). */
    private function requireDraftField(Entity $entity, Field $field): void
    {
        $this->requireDraft($entity);

        if ($field->entity_version_id !== $entity->draft_version_id) {
            throw new NotFoundHttpException;
        }
    }
}
