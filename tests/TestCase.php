<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Setiap test database berisi hasil bootstrap platform (root Pemda, role bawaan, admin). */
    protected bool $seed = true;

    protected string $seeder = PlatformBootstrapSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Feature test tidak bergantung pada hasil build frontend.
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
