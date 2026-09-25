<?php

declare(strict_types=1);

namespace App\Modules\Eventing\Infrastructure;

use App\Modules\Eventing\Contracts\EventRecorder;
use App\Shared\Support\TraceContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use LogicException;

final readonly class OutboxEventRecorder implements EventRecorder
{
    public const int PAYLOAD_VERSION = 1;

    public function __construct(
        private ConnectionInterface $db,
        private TraceContext $trace,
    ) {}

    public function record(string $eventType, string $aggregateType, string $aggregateId, array $payload = []): string
    {
        // Event di luar transaksi bisa lolos padahal perubahan data di-rollback (event hantu).
        if ($this->db->transactionLevel() === 0) {
            throw new LogicException("Event [{$eventType}] harus dicatat di dalam transaksi database.");
        }

        $id = Str::uuid7()->toString();

        $this->db->table('outbox_events')->insert([
            'id' => $id,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => json_encode(['v' => self::PAYLOAD_VERSION, ...$payload], JSON_THROW_ON_ERROR),
            'trace_id' => $this->trace->id(),
        ]);

        return $id;
    }
}
