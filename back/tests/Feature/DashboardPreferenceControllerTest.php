<?php

namespace Tests\Feature;

use App\Models\DashboardPreference;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_default_layout_when_user_has_no_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.preferences.show'));

        $response->assertOk()
            ->assertJsonPath('layout.main.device_id', null)
            ->assertJsonPath('layout.main.sensor_id', null)
            ->assertJsonPath('layout.monitors', []);
    }

    public function test_store_normalizes_missing_main_fields_and_monitors(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        $response = $this->actingAs($user)->postJson(route('dashboard.preferences.store'), [
            'layout' => [
                'main' => [
                    'device_id' => $device->id,
                    'sensor_id' => $sensor->id,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('layout.main.device_id', $device->id)
            ->assertJsonPath('layout.main.sensor_id', $sensor->id)
            ->assertJsonPath('layout.monitors', []);

        $preferences = DashboardPreference::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($preferences);
        $this->assertSame($device->id, $preferences->layout['main']['device_id']);
        $this->assertSame($sensor->id, $preferences->layout['main']['sensor_id']);
        $this->assertSame([], $preferences->layout['monitors']);
    }

    public function test_store_persists_selected_id_and_each_widget_range(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        $layout = [
            'main' => [
                'id' => 'main',
                'device_id' => $device->id,
                'sensor_id' => $sensor->id,
                'range' => '1h',
            ],
            'monitors' => [[
                'id' => 'monitor-1',
                'device_id' => $device->id,
                'sensor_id' => $sensor->id,
                'range' => '24h',
            ]],
            'selected_id' => 'monitor-1',
        ];

        $response = $this->actingAs($user)->postJson(route('dashboard.preferences.store'), compact('layout'));

        $response->assertOk()
            ->assertJsonPath('layout.selected_id', 'monitor-1')
            ->assertJsonPath('layout.main.range', '1h')
            ->assertJsonPath('layout.monitors.0.range', '24h');

        $stored = DashboardPreference::query()->where('user_id', $user->id)->first()->layout;
        $this->assertSame('monitor-1', $stored['selected_id']);
        $this->assertSame('1h', $stored['main']['range']);
        $this->assertSame('24h', $stored['monitors'][0]['range']);
    }

    public function test_store_rejects_invalid_widget_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('dashboard.preferences.store'), [
            'layout' => [
                'main' => ['range' => '30d'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('layout.main.range');
    }
}
