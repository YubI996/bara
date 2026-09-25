<?php

declare(strict_types=1);

/*
 * Aturan dependensi modul (docs/03 §3, ADR 0001).
 * Modul hanya boleh memakai modul lain lewat namespace Contracts-nya.
 */

$modules = array_map(
    'basename',
    glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR) ?: [],
);

$internals = ['Actions', 'Console', 'Data', 'Enums', 'Http', 'Infrastructure', 'Listeners', 'Models', 'Policies', 'Providers'];

foreach ($modules as $module) {
    $forbidden = [];

    foreach ($modules as $other) {
        if ($other === $module) {
            continue;
        }

        foreach ($internals as $internal) {
            $forbidden[] = "App\\Modules\\{$other}\\{$internal}";
        }
    }

    arch("modul {$module} hanya memakai Contracts modul lain")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse($forbidden);
}

arch('Contracts tidak bergantung pada implementasi')
    ->expect('App\Modules\*\Contracts')
    ->not->toUse(['App\Modules\*\Infrastructure', 'Illuminate\Support\Facades']);

arch('Audit dan Eventing tidak bergantung pada modul lain')
    ->expect(['App\Modules\Audit', 'App\Modules\Eventing'])
    ->not->toUse(['App\Modules\Access', 'App\Modules\Data', 'App\Modules\Metadata', 'App\Modules\Organization']);

arch('controller modul tidak memakai query builder atau DB facade langsung')
    ->expect('App\Modules\*\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\ConnectionInterface']);

arch('Action memakai kelas final readonly')
    ->expect('App\Modules\*\Actions')
    ->classes()
    ->toBeFinal();
