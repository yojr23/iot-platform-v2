<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Dotenv\Dotenv;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cargar explícitamente el archivo .env.testing
        if (file_exists(base_path('.env.testing'))) {
            Dotenv::createImmutable(base_path(), '.env.testing')->load();
        }

    // Configurar la conexión de base de datos para las pruebas tomando el valor
    // de la variable de entorno DB_CONNECTION (por ejemplo: sqlite o mysql).
    // Esto permite usar SQLite in-memory cuando se desee.
    $testConnection = env('DB_CONNECTION', config('database.default'));
    config()->set('database.default', $testConnection);
    }
}
