<?php

declare(strict_types=1);

namespace App\Modules\Data\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\AccessChecker;
use App\Modules\Data\Actions\CreateRecord;
use App\Modules\Data\Actions\DeleteRecord;
use App\Modules\Data\Actions\UpdateRecord;
use App\Modules\Data\Http\Presenters\RecordPresenter;
use App\Modules\Data\Runtime\FieldGate;
use App\Modules\Data\Runtime\FileStore;
use App\Modules\Data\Runtime\JsonData;
use App\Modules\Data\Runtime\RecordFilters;
use App\Modules\Data\Runtime\RecordValidator;
use App\Modules\Data\Runtime\RecordWriter;
use App\Modules\Data\Runtime\RelationTargets;
use App\Modules\Data\Runtime\ScopedRecordQuery;
use App\Modules\Data\Runtime\ScopeFactory;
use App\Modules\Metadata\Contracts\EntitySchema;
use App\Modules\Metadata\Contracts\SchemaRepository;
use App\Modules\Organization\Contracts\OrganizationDirectory;
use App\Modules\Organization\Contracts\OrganizationSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Satu controller generik untuk semua entity terbit (docs/06 §3). Tidak ada kode per aplikasi.
 */
final readonly class RecordController
{
    public function __construct(
        private SchemaRepository $schemas,
        private ScopeFactory $scopes,
        private ScopedRecordQuery $query,
        private AccessChecker $access,
        private RecordPresenter $presenter,
        private RelationTargets $targets,
        private RecordWriter $writer,
        private OrganizationDirectory $organizations,
        private FileStore $files,
        private RecordFilters $filters,
    ) {}

    /** Daftar entity yang boleh dilihat user, dikelompokkan per aplikasi. */
    public function home(Request $request): Response
    {
        $user = $this->user($request);
        $groups = [];

        foreach ($this->schemas->allPublished() as $schema) {
            if (! $this->scopes->canView($user, $schema)) {
                continue;
            }
            $groups[$schema->applicationCode]['name'] = $schema->applicationName;
            $groups[$schema->applicationCode]['code'] = $schema->applicationCode;
            $groups[$schema->applicationCode]['entities'][] = [
                'code' => $schema->entityCode,
                'name' => $schema->entityNamePlural,
            ];
        }

        return Inertia::render('runtime/home', ['applications' => array_values($groups)]);
    }

    public function index(Request $request, string $app, string $entity): Response
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $gate = $this->gate($user, $schema);
        $search = $request->string('q')->trim()->limit(100, '')->toString();

        $query = $this->query->records($schema->entityId, $this->scopes->read($user, $schema));
        $this->filters->search($query, $search);
        $f = $request->input('f', []);
        $filters = $this->filters->apply($query, $schema, $gate, is_array($f) ? $f : []);

        $page = $query
            ->orderByDesc('r.created_at')
            ->orderByDesc('r.id')
            ->cursorPaginate(config()->integer('bara.records.page_size'), ['r.id', 'r.title', 'r.data', 'r.created_at', 'o.owner_org_id'], 'cursor')
            ->withQueryString();

        $rows = [];
        foreach ($page->items() as $row) {
            if (! is_object($row) || ! isset($row->id, $row->title)) {
                continue;
            }
            $rows[] = [
                'id' => $row->id,
                'title' => $row->title,
                'values' => $this->presenter->values($schema, $gate, JsonData::decode($row->data ?? null), listOnly: true),
            ];
        }

        return Inertia::render('runtime/index', [
            'entity' => $this->presenter->entity($schema),
            'fields' => $this->presenter->fields($schema, $gate),
            'rows' => $rows,
            'filters' => ['q' => $search, 'f' => (object) $filters],
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
            'can' => ['create' => $this->scopes->can($user, $schema, 'create')],
        ]);
    }

    public function create(Request $request, string $app, string $entity): Response
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $this->ensure($this->scopes->can($user, $schema, 'create'));

        return $this->form($user, $schema, null);
    }

    public function store(Request $request, string $app, string $entity, RecordValidator $validator, CreateRecord $action): RedirectResponse
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $this->ensure($this->scopes->can($user, $schema, 'create'));

        $input = $validator->validate($schema, $this->gate($user, $schema), $user, $this->arrayInput($request, 'data'), $this->arrayFiles($request));
        $id = $action->execute($schema, $user, $this->scopes->write($user, $schema, 'create'), $request->string('owner_org_id')->toString(), $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$schema->entityName} tersimpan."]);

        return redirect()->route('runtime.show', ['app' => $app, 'entity' => $entity, 'record' => $id]);
    }

    public function show(Request $request, string $app, string $entity, string $record): Response
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $row = $this->find($user, $schema, $record);
        $gate = $this->gate($user, $schema);
        $data = JsonData::decode($row->data ?? null);
        $links = $this->writer->links($schema, $record);

        return Inertia::render('runtime/show', [
            'entity' => $this->presenter->entity($schema),
            'fields' => $this->presenter->fields($schema, $gate),
            'record' => [
                'id' => $record,
                'title' => $row->title ?? '',
                'values' => $this->presenter->values($schema, $gate, $data, $links, $this->targets->titles(array_merge([], ...array_values($links))), $this->fileInfo($record, $app, $entity)),
                'owner_name' => is_string($row->owner_org_id ?? null) ? $this->organizations->find($row->owner_org_id)?->name : null,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
                'version' => ($row->entity_version_id ?? null) === $schema->entityVersionId ? $schema->version : null,
            ],
            'can' => [
                'update' => $this->covers($user, $schema, 'update', $row),
                'delete' => $this->covers($user, $schema, 'delete', $row),
            ],
        ]);
    }

    public function edit(Request $request, string $app, string $entity, string $record): Response
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $row = $this->find($user, $schema, $record);
        $this->ensure($this->covers($user, $schema, 'update', $row));
        $gate = $this->gate($user, $schema);
        $links = $this->writer->links($schema, $record);

        return $this->form($user, $schema, [
            'id' => $record,
            'title' => $row->title ?? '',
            'lock_version' => is_numeric($row->lock_version ?? null) ? (int) $row->lock_version : 0,
            'values' => $this->presenter->values($schema, $gate, JsonData::decode($row->data ?? null), $links, $this->targets->titles(array_merge([], ...array_values($links))), $this->fileInfo($record, $app, $entity)),
        ]);
    }

    public function update(Request $request, string $app, string $entity, string $record, RecordValidator $validator, UpdateRecord $action): RedirectResponse
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $this->ensure($this->scopes->can($user, $schema, 'update'));
        $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);

        $input = $validator->validate($schema, $this->gate($user, $schema), $user, $this->arrayInput($request, 'data'), $this->arrayFiles($request));
        $action->execute($schema, $user, $this->scopes->write($user, $schema, 'update'), $record, $request->integer('lock_version'), $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan tersimpan.']);

        return redirect()->route('runtime.show', ['app' => $app, 'entity' => $entity, 'record' => $record]);
    }

    public function destroy(Request $request, string $app, string $entity, string $record, DeleteRecord $action): RedirectResponse
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $this->ensure($this->scopes->can($user, $schema, 'delete'));
        $action->execute($schema, $this->scopes->write($user, $schema, 'delete'), $record);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$schema->entityName} dihapus."]);

        return redirect()->route('runtime.index', ['app' => $app, 'entity' => $entity]);
    }

    public function download(Request $request, string $app, string $entity, string $record, string $file, Filesystems $filesystems): StreamedResponse
    {
        $user = $this->user($request);
        $schema = $this->viewable($user, $app, $entity);
        $this->find($user, $schema, $record);
        $info = $this->files->find($file, $record);
        $field = $info === null ? null : $schema->fieldByKey($info['field_key']);

        // Berkas hanya boleh diunduh bila field-nya terlihat penuh dan sudah lolos pindai.
        if ($info === null || $field === null || ! $info['clean']
            || $this->gate($user, $schema)->access($field) !== FieldGate::VISIBLE) {
            throw new NotFoundHttpException;
        }

        return $filesystems->disk($info['disk'])->download($info['path'], $info['name'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param  array<string, mixed>|null  $record */
    private function form(User $user, EntitySchema $schema, ?array $record): Response
    {
        $gate = $this->gate($user, $schema);
        $relationOptions = [];

        foreach ($schema->fields as $field) {
            $target = $field->configValue('target_entity_id');
            if ($field->type === 'relationship' && is_string($target) && $gate->canWrite($field)) {
                $relationOptions[$field->code] = $this->targets->options($user, $target);
            }
        }

        $owners = array_map(
            static fn (OrganizationSummary $o): array => ['value' => $o->id, 'label' => $o->name, 'depth' => $o->depth],
            $this->organizations->activeWithin($this->access->grants($user, $schema->permission('create'))),
        );

        return Inertia::render('runtime/form', [
            'entity' => $this->presenter->entity($schema),
            'fields' => $this->presenter->fields($schema, $gate, $relationOptions),
            'record' => $record,
            'owners' => $owners,
            'default_owner_id' => in_array($user->primary_org_id, array_column($owners, 'value'), true) ? $user->primary_org_id : ($owners[0]['value'] ?? null),
            'timezone' => config()->string('bara.pemda.timezone'),
        ]);
    }

    private function viewable(User $user, string $app, string $entity): EntitySchema
    {
        $schema = $this->schemas->published($app, $entity);

        if ($schema === null) {
            throw new NotFoundHttpException;
        }

        $this->ensure($this->scopes->canView($user, $schema));

        return $schema;
    }

    private function find(User $user, EntitySchema $schema, string $record): object
    {
        $row = Str::isUuid($record)
            ? $this->query->records($schema->entityId, $this->scopes->read($user, $schema))
                ->where('r.id', $record)
                ->first(['r.id', 'r.title', 'r.data', 'r.lock_version', 'r.entity_version_id', 'r.created_at', 'r.updated_at', 'o.owner_org_id', 'o.owner_path', 'o.visibility'])
            : null;

        if (! is_object($row)) {
            throw new NotFoundHttpException;
        }

        return $row;
    }

    private function covers(User $user, EntitySchema $schema, string $action, object $row): bool
    {
        return is_string($row->owner_path ?? null)
            && $this->access->allows($user, $schema->permission($action), $row->owner_path);
    }

    private function gate(User $user, EntitySchema $schema): FieldGate
    {
        return new FieldGate($this->access->clearance($user, $schema->applicationId));
    }

    /** @return array<string, array<string, mixed>> */
    private function fileInfo(string $record, string $app, string $entity): array
    {
        $info = [];
        foreach ($this->files->forObject($record) as $id => $file) {
            $info[$id] = [
                'id' => $id,
                'name' => $file['name'],
                'size' => $file['size'],
                'available' => $file['available'],
                'url' => route('runtime.files.download', ['app' => $app, 'entity' => $entity, 'record' => $record, 'file' => $id]),
            ];
        }

        return $info;
    }

    /** @return array<mixed> */
    private function arrayInput(Request $request, string $key): array
    {
        $value = $request->input($key, []);

        return is_array($value) ? $value : [];
    }

    /** @return array<mixed> */
    private function arrayFiles(Request $request): array
    {
        $files = $request->file('files', []);

        return is_array($files) ? $files : [];
    }

    private function ensure(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
