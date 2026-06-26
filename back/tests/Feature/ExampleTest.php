<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // La ruta raíz redirige al dashboard; en entorno de testing puede devolver 302.
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
    }
}
