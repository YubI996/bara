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
