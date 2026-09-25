<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Transactional outbox (ADR 0010). Relay dibangun di M10; M0 menyediakan penulisan event. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE outbox_events (
                id             uuid PRIMARY KEY DEFAULT uuidv7(),
                event_type     text NOT NULL CHECK (event_type ~ '^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$'),
                aggregate_type text NOT NULL,
                aggregate_id   uuid NOT NULL,
                payload        jsonb NOT NULL,
                trace_id       text,
                occurred_at    timestamptz NOT NULL DEFAULT now(),
                published_at   timestamptz,
                attempts       integer NOT NULL DEFAULT 0,
                last_error     text
            );
            CREATE INDEX outbox_unpublished ON outbox_events (occurred_at) WHERE published_at IS NULL;

            CREATE TABLE processed_events (
                listener     text NOT NULL,
                event_id     uuid NOT NULL,
                processed_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (listener, event_id)
            );
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS processed_events, outbox_events');
    }
};
