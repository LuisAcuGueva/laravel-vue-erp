<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Database\Seeders\UbigeoSeeder;

trait SeedsUbigeoFixture
{
    /**
     * Siembra el catálogo de ubigeo con el CSV reducido de `tests/Fixtures`.
     */
    protected function seedUbigeoFixture(string $fixture = 'ubigeo_sample.csv'): void
    {
        $seeder = new UbigeoSeeder;
        $seeder->path = base_path("tests/Fixtures/{$fixture}");
        $seeder->run();
    }
}
