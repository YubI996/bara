<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/** Satu cakupan akses: path organisasi (ltree) dan apakah turunannya ikut. */
final readonly class ScopeGrant
{
    public function __construct(
        public string $path,
        public bool $includeDescendants,
    ) {}

    public function covers(string $targetPath): bool
    {
        if ($targetPath === $this->path) {
            return true;
        }

        return $this->includeDescendants && str_starts_with($targetPath, $this->path.'.');
    }
}
