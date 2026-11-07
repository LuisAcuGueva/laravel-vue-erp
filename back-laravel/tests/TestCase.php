<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function setUpTraits()
    {
        // Antes de RefreshDatabase: nunca migrar sobre una BD que no sea de pruebas.
        $this->assertSafeTestDatabase();

        return parent::setUpTraits();
    }

    /**
     * Aborta si los tests apuntan a una BD que no sea de pruebas (evita borrar laravel_dev).
     */
    private function assertSafeTestDatabase(): void
    {
        $config = config('database.connections.'.config('database.default'));
        $database = (string) ($config['database'] ?? '');
        $port = (string) ($config['port'] ?? '');

        if (! str_ends_with($database, '_test') || ($port === '5432' && ($config['host'] ?? '') !== 'postgres-test')) {
            $this->fail("Tests abortados: la BD '{$database}' ({$config['host']}:{$port}) no es de pruebas. "
                .'Ejecuta los tests con `make test-run` (o `make test-filter FILTER=...`).');
        }
    }
}
