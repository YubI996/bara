<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Actions;

use App\Models\User;
use App\Modules\Access\Contracts\PermissionRegistry;
use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Eventing\Contracts\EventRecorder;
use App\Modules\Metadata\Contracts\EntityVersionPublished;
use App\Modules\Metadata\Models\Entity;
use App\Modules\Metadata\Models\EntityVersion;
use App\Modules\Metadata\Models\Relationship;
use App\Modules\Metadata\Schema\ChangeCategory;
use App\Modules\Metadata\Schema\DraftReport;
use App\Modules\Metadata\Schema\FieldChange;
use App\Modules\Metadata\Schema\SchemaCompiler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Publikasi draft (docs/06 §2): validasi → diff → gate PDP → compile → persist → side effect.
 * Versi terbit bersifat immutable (dijaga trigger DB).
 */
final readonly class PublishEntityVersion
{
    /** @var array<string, string> */
    public const array ENTITY_ACTIONS = [
        'view' => 'Melihat',
        'create' => 'Menambah',
        'update' => 'Mengubah',
        'delete' => 'Menghapus',
        'export' => 'Mengekspor',
    ];

    public function __construct(
        private ConnectionInterface $db,
        private InspectDraft $inspect,
        private SchemaCompiler $compiler,
        private PermissionRegistry $permissions,
        private AuditLogger $audit,
        private EventRecorder $events,
        private Dispatcher $dispatcher,
    ) {}

    public function execute(Entity $entity, User $publisher, ?string $note = null): EntityVersion
    {
        return $this->db->transaction(function () use ($entity, $publisher, $note): EntityVersion {
            $draft = DraftSupport::lockDraft($entity);
            $entity = Entity::query()->with('application')->findOrFail($entity->id);
            $report = $this->inspect->execute($entity, $draft);

            if (! $report->canPublish()) {
                throw MetadataRuleViolation::on('publish', $report->blockers());
            }

            if ($report->requiresPrivacyReview && $draft->privacy_reviewed_at === null) {
                throw MetadataRuleViolation::on('publish', 'Draft memuat perubahan data pribadi dan harus disetujui Pejabat PDP (role dpo) sebelum dipublikasikan.');
            }

            $fields = $draft->definitions();
            $compiled = $this->compiler->compile(DraftSupport::context($entity), $draft->version, $fields);

            if ($entity->published_version_id !== null) {
                EntityVersion::query()->whereKey($entity->published_version_id)->update(['status' => 'superseded']);
            }

            $draft->forceFill([
                'status' => 'published',
                'compiled_schema' => $compiled,
                'change_summary' => $this->summary($report, $note),
                'published_at' => now(),
                'published_by' => $publisher->id,
            ])->save();

            $entity->forceFill(['published_version_id' => $draft->id, 'draft_version_id' => null])->save();

            $this->syncRelationships($entity, $compiled);
            $this->registerPermissions($entity);
            $this->dispatcher->dispatch(new EntityVersionPublished($entity->id, $draft->id, $entity->application_id));

            $this->audit->log('metadata.publish', $entity->id, 'metadata.entity', context: [
                'version' => $draft->version,
                'fields' => count($fields),
                'changes' => count($report->changes),
                'worst_change' => $this->worst($report)->value,
            ]);
            $this->events->record('metadata.published', 'metadata.entity', $entity->id, [
                'entity_version_id' => $draft->id,
                'version' => $draft->version,
                'application_code' => $entity->application->code,
                'entity_code' => $entity->code,
            ]);

            return $draft;
        });
    }

    /** @param  array<string, mixed>  $compiled */
    private function syncRelationships(Entity $entity, array $compiled): void
    {
        $active = [];

        foreach (is_array($compiled['relationships']) ? $compiled['relationships'] : [] as $relationship) {
            if (! is_array($relationship) || ! is_string($relationship['field_key'] ?? null)) {
                continue;
            }

            $active[] = $relationship['field_key'];
            Relationship::query()->updateOrCreate(
                ['source_entity_id' => $entity->id, 'field_key' => $relationship['field_key']],
                [
                    'target_entity_id' => $relationship['target_entity_id'],
                    'code' => $relationship['code'],
                    'cardinality' => $relationship['cardinality'],
                    'on_target_delete' => $relationship['on_target_delete'],
                    'inverse_code' => $relationship['inverse_code'],
                    'is_active' => true,
                ],
            );
        }

        Relationship::query()->where('source_entity_id', $entity->id)->whereNotIn('field_key', $active)
            ->update(['is_active' => false]);
    }

    private function registerPermissions(Entity $entity): void
    {
        $permissions = [];
        foreach (self::ENTITY_ACTIONS as $action => $verb) {
            $permissions["{$entity->application->code}.{$entity->code}.{$action}"] =
                "{$verb} data {$entity->name} ({$entity->application->name})";
        }

        $this->permissions->register($permissions);
        $this->permissions->grantEntityToApplicationRoles(
            $entity->application_id,
            $entity->application->code,
            $entity->code,
            array_keys(self::ENTITY_ACTIONS),
        );
    }

    private function summary(DraftReport $report, ?string $note): string
    {
        $counts = ['added' => 0, 'modified' => 0, 'removed' => 0];
        foreach ($report->changes as $change) {
            $counts[$change->kind]++;
        }

        $summary = sprintf('%d field ditambah, %d diubah, %d dihapus.', $counts['added'], $counts['modified'], $counts['removed']);

        return $note !== null && trim($note) !== '' ? trim($note).' — '.$summary : $summary;
    }

    private function worst(DraftReport $report): ChangeCategory
    {
        return ChangeCategory::worst(...array_map(static fn (FieldChange $c): ChangeCategory => $c->category, $report->changes));
    }
}
