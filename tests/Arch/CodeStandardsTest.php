<?php

declare(strict_types=1);

arch('semua kode aplikasi memakai strict types')
    ->expect(['App', 'Database\Seeders', 'Database\Factories'])
    ->toUseStrictTypes();

arch('tidak ada fungsi debug yang tertinggal')
    ->preset()
    ->php();

arch('tidak ada fungsi berbahaya (eval, exec, dsb.)')
    ->preset()
    ->security()
    ->ignoring('App\Shared\Support');

arch('tidak memakai symfony/expression-language (ADR 0008)')
    ->expect('App')
    ->not->toUse('Symfony\Component\ExpressionLanguage');

test('setiap file PHP di luar vendor (config, migration, route, tes) memakai strict types', function (): void {
    $root = dirname(__DIR__, 2);
    $missing = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'lang', 'public'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || str_contains($path, '/bootstrap/cache/')) {
                continue;
            }
            if (! str_contains((string) file_get_contents($path), 'declare(strict_types=1);')) {
                $missing[] = substr($path, strlen($root) + 1);
            }
        }
    }

    expect($missing)->toBe([]);
});
