<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase7DockerReadinessTest extends TestCase
{
    public function test_health_endpoint_still_returns_json(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    public function test_sanctum_stateful_domains_keep_local_frontend_hosts(): void
    {
        $stateful = config('sanctum.stateful', []);

        $this->assertTrue(
            collect($stateful)->contains(fn (string $domain) => str_starts_with($domain, 'localhost:')),
            'Sanctum stateful domains should include at least one localhost entry.'
        );

        $this->assertTrue(
            collect($stateful)->contains(fn (string $domain) => str_starts_with($domain, '127.0.0.1:')),
            'Sanctum stateful domains should include at least one 127.0.0.1 entry.'
        );

        $expectedPorts = array_unique(array_filter([
            parse_url(config('app.front_url'), PHP_URL_PORT),
            parse_url(config('app.url'), PHP_URL_PORT),
        ]));

        foreach ($expectedPorts as $port) {
            $this->assertContains("localhost:$port", $stateful);
            $this->assertContains("127.0.0.1:$port", $stateful);
        }
    }

    public function test_application_base_path_is_back_directory(): void
    {
        $this->assertSame('back', basename(base_path()));
        $this->assertFileExists(base_path('routes/api.php'));
        $this->assertFileExists(base_path('artisan'));
    }
}
