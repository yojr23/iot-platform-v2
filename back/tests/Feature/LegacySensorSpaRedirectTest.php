<?php

namespace Tests\Feature;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacySensorSpaRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_sensors_index_redirects_to_canonical_spa(): void
    {
        config(['app.front_url' => 'http://frontend.test']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/sensors');

        $response->assertRedirect('http://frontend.test/sensors');
    }

    public function test_authenticated_sensor_show_redirects_to_canonical_spa(): void
    {
        config(['app.front_url' => 'http://frontend.test']);
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $response = $this->actingAs($user)->get("/sensors/{$sensor->id}");

        $response->assertRedirect("http://frontend.test/sensors/{$sensor->id}");
    }

    public function test_unauthenticated_sensors_index_hits_auth_middleware_not_the_spa(): void
    {
        config(['app.front_url' => 'http://frontend.test']);

        $response = $this->get('/sensors');

        $response->assertRedirect();
        $this->assertStringNotContainsString('frontend.test', $response->headers->get('Location'));
    }
}
