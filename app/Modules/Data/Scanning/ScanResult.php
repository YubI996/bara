<?php

declare(strict_types=1);

namespace App\Modules\Data\Scanning;

final readonly class ScanResult
{
    /** @param  'clean'|'infected'|'error'  $status */
    private function __construct(public string $status, public ?string $detail) {}

    public static function clean(): self
    {
        return new self('clean', null);
    }

    public static function infected(string $signature): self
    {
        return new self('infected', mb_substr($signature, 0, 200));
    }

    public static function error(string $reason): self
    {
        return new self('error', mb_substr($reason, 0, 200));
    }
}
