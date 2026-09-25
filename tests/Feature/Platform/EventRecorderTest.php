<?php

declare(strict_types=1);

use App\Modules\Eventing\Contracts\EventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('event dicatat ke outbox dengan versi payload dan trace id', function (): void {
    $id = DB::transaction(fn (): string => app(EventRecorder::class)
        ->record('record.created', 'test.entity', Str::uuid7()->toString(), ['foo' => 'bar']));

    $row = DB::table('outbox_events')->where('id', $id)->first();

    expect($row)->not->toBeNull()
        ->and(json_decode($row->payload, true))->toBe(['v' => 1, 'foo' => 'bar'])
        ->and($row->trace_id)->not->toBeNull()
        ->and($row->published_at)->toBeNull();
});

test('event di luar transaksi ditolak (mencegah event hantu)', function (): void {
    // RefreshDatabase membungkus test dalam transaksi; keluar dulu agar level = 0.
    DB::rollBack();

    try {
        expect(fn () => app(EventRecorder::class)->record('record.created', 'x', Str::uuid7()->toString()))
            ->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

test('event yang dicatat ikut di-rollback bersama transaksinya', function (): void {
    try {
        DB::transaction(function (): void {
            app(EventRecorder::class)->record('record.created', 'x', Str::uuid7()->toString());
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('outbox_events')->count())->toBe(0);
});
