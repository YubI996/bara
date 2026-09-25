<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Str;

/**
 * Trace id per request/job. Dibawa ke log, audit, dan outbox (docs/03 §8).
 */
final class TraceContext
{
    private ?string $traceId = null;

    public function id(): string
    {
        return $this->traceId ??= Str::uuid7()->toString();
    }

    public function set(string $traceId): void
    {
        $this->traceId = $traceId;
    }
}
