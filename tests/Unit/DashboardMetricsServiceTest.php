<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_stats_structure(): void
    {
        Device::factory()->count(2)->create();
        Device::factory()->create(['status' => true]);
        $alert = Alert::factory()->create(['resolved' => false]);

        $service = new DashboardMetricsService();
        $stats = $service->getSummaryStats();

        $this->assertArrayHasKey('totalDevices', $stats);
        $this->assertArrayHasKey('activeDevices', $stats);
        $this->assertArrayHasKey('activeAlerts', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['totalDevices']);
        $this->assertIsInt($stats['activeAlerts']);
    }

    public function test_active_alerts_list_returns_collection(): void
    {
        $service = new DashboardMetricsService();
        $this->assertTrue($service->getActiveAlertsList() instanceof \Illuminate\Support\Collection);
    }

    public function test_devices_for_selection_are_ordered(): void
    {
        Device::factory()->create(['name' => 'Zeta']);
        Device::factory()->create(['name' => 'Alpha']);

        $service = new DashboardMetricsService();
        $devices = $service->getDevicesForSelection();

        $this->assertEquals('Alpha', $devices->first()->name);
    }
}
