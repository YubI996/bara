<?php

declare(strict_types=1);

namespace App\Modules\Eventing\Contracts;

/**
 * Menulis domain event ke outbox dalam transaksi yang sedang berjalan (ADR 0010).
 * Payload tidak boleh memuat data pribadi: cukup id dan field yang berubah.
 */
interface EventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return string id event
     */
    public function record(string $eventType, string $aggregateType, string $aggregateId, array $payload = []): string;
}
