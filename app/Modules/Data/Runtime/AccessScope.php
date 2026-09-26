<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use App\Modules\Access\Contracts\ScopeGrant;

/**
 * Cakupan baca/tulis satu user untuk satu aksi (docs/05 §1). `system` hanya untuk job internal
 * (SystemContext) dan tidak pernah dibuat dari request pengguna.
 */
final readonly class AccessScope
{
    /** @param  list<ScopeGrant>  $grants */
    private function __construct(
        public array $grants,
        public bool $includeVisibility,
        public bool $isInternal,
        public bool $system,
    ) {}

    /**
     * Cakupan baca: grant scope + visibilitas objek.
     *
     * @param  list<ScopeGrant>  $grants
     */
    public static function read(array $grants, bool $isInternal): self
    {
        return new self($grants, true, $isInternal, false);
    }

    /**
     * Cakupan tulis: hanya grant scope (visibilitas tidak pernah memberi hak ubah).
     *
     * @param  list<ScopeGrant>  $grants
     */
    public static function write(array $grants): self
    {
        return new self($grants, false, false, false);
    }

    public static function system(): self
    {
        return new self([], false, true, true);
    }

    public function isEmpty(): bool
    {
        return ! $this->system && $this->grants === [] && ! $this->includeVisibility;
    }

    public function covers(string $ownerPath, string $visibility): bool
    {
        if ($this->system) {
            return true;
        }

        foreach ($this->grants as $grant) {
            if ($grant->covers($ownerPath)) {
                return true;
            }
        }

        return $this->includeVisibility && match ($visibility) {
            'public' => true,
            'internal', 'partner' => $this->isInternal,
            default => false,
        };
    }
}
