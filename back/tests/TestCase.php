<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Throwable;

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

        // Aislar Redis en pruebas evita lecturas cacheadas entre tests cuando
        // el entorno de Docker dispone de un servidor Redis real.
        config()->set('database.redis.default.database', 15);
        config()->set('database.redis.cache.database', 15);

        try {
            Redis::connection('default')->flushdb();
        } catch (Throwable) {
            // Algunos entornos de prueba no exponen Redis; en ese caso se usa fallback.
        }
    }
}
