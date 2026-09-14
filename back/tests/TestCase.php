<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('roles')) {
            $this->seed(RolePermissionSeeder::class);
        }

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
