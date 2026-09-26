<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
 * Syarat M2: daftar 100.000 record dengan filter field terindeks, p95 < 300 ms (server).
 * Berat (±20 dtk), jadi hanya berjalan bila BARA_PERF=1:  BARA_PERF=1 php artisan test --filter=ListPerformance
 */
test('daftar 100.000 record dengan filter terindeks: p95 < 300 ms', function (): void {
    $owner = createOrganization('dinkes');
    $app = createApplication('monev');
    $entity = createEntity($app, 'kegiatan', titleTemplate: '{nama}');
    addField($entity, 'nama', 'string', required: true);
    addField($entity, 'status', 'enum', ['options' => [['value' => 'rencana', 'label' => 'Rencana'], ['value' => 'berjalan', 'label' => 'Berjalan'], ['value' => 'selesai', 'label' => 'Selesai']]], indexed: true);
    publishEntity($entity);
    $entity->refresh();

    $keys = DB::table('fields')->where('entity_version_id', $entity->published_version_id)->pluck('field_key', 'code');
    $user = userWithAppRole($app, 'operator', $owner);

    DB::statement(<<<'SQL'
        WITH ids AS (SELECT uuidv7() AS id, g FROM generate_series(1, 100000) g),
        ins_obj AS (
            INSERT INTO objects (id, entity_id, owner_org_id, owner_path, visibility)
            SELECT id, ?, ?, ?::ltree, 'private' FROM ids
        )
        INSERT INTO records (id, entity_id, entity_version_id, data, title, created_by, updated_by, created_at)
        SELECT id, ?, ?, jsonb_build_object(?::text, 'Kegiatan ' || g, ?::text, (ARRAY['rencana','berjalan','selesai'])[1 + g % 3]),
               'Kegiatan ' || g, ?, ?, now() - (g || ' seconds')::interval
        FROM ids
        SQL, [
        $entity->id, $owner->id, $owner->path,
        $entity->id, $entity->published_version_id, $keys['nama'], $keys['status'], $user->id, $user->id,
    ]);
    DB::statement('ANALYZE records');
    DB::statement('ANALYZE objects');

    $measure = function (array $query, int $expectedRows) use ($user): array {
        $url = route('runtime.index', ['app' => 'monev', 'entity' => 'kegiatan', ...$query]);
        $assert = fn ($response) => $response->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('rows', $expectedRows));
        $assert($this->actingAs($user)->get($url)); // pemanasan

        $times = [];
        foreach (range(1, 40) as $i) {
            $start = hrtime(true);
            $assert($this->actingAs($user)->get($url));
            $times[] = (hrtime(true) - $start) / 1e6;
        }
        sort($times);

        return [$times[19], $times[(int) ceil(0.95 * count($times)) - 1]];
    };

    [$p50, $p95] = $measure(['f' => ['status' => 'selesai']], 25);
    [$s50, $s95] = $measure(['q' => 'Kegiatan 99999'], 1);

    fwrite(STDERR, sprintf("\n[perf] 100k record — filter status: p50 %.1f ms, p95 %.1f ms | cari judul: p50 %.1f ms, p95 %.1f ms\n", $p50, $p95, $s50, $s95));
    expect($p95)->toBeLessThan(300.0)->and($s95)->toBeLessThan(300.0);
})->skip(fn (): bool => getenv('BARA_PERF') !== '1', 'Set BARA_PERF=1 untuk menjalankan uji performa.');
