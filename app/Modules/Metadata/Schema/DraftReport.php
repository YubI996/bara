<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

/** Hasil pemeriksaan draft sebelum publikasi. */
final readonly class DraftReport
{
    /**
     * @param  list<string>  $errors  pesan yang menghalangi publikasi
     * @param  list<string>  $warnings  pesan yang tidak menghalangi
     * @param  list<FieldChange>  $changes
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public array $changes,
        public bool $hasChanges,
        public bool $requiresPrivacyReview,
    ) {}

    public function canPublish(): bool
    {
        return $this->errors === [] && $this->hasChanges
            && ChangeClassifier::worst($this->changes) !== ChangeCategory::Blocked;
    }

    /** @return list<string> alasan publikasi ditolak */
    public function blockers(): array
    {
        $blockers = $this->errors;

        if (! $this->hasChanges) {
            $blockers[] = 'Tidak ada perubahan dibanding versi yang sudah terbit.';
        }

        foreach ($this->changes as $change) {
            if ($change->category === ChangeCategory::Blocked) {
                foreach ($change->messages as $message) {
                    $blockers[] = "{$change->label}: {$message}";
                }
            }
        }

        return $blockers;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'changes' => array_map(static fn (FieldChange $c): array => $c->toArray(), $this->changes),
            'has_changes' => $this->hasChanges,
            'requires_privacy_review' => $this->requiresPrivacyReview,
            'can_publish' => $this->canPublish(),
            'blockers' => $this->blockers(),
        ];
    }
}
