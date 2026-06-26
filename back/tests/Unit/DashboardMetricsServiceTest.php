<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_stats_structure(): void
    {
        Device::factory()->count(2)->create();
        Device::factory()->create(['status' => true]);

        $service = app(DashboardMetricsService::class);
        $stats = $service->getSummaryStats();

        $this->assertArrayHasKey('totalDevices', $stats);
        $this->assertArrayHasKey('activeDevices', $stats);
        $this->assertArrayHasKey('activeAlerts', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['totalDevices']);
        $this->assertIsInt($stats['activeAlerts']);
    }

    public function test_active_alerts_list_returns_collection(): void
    {
        $service = app(DashboardMetricsService::class);
        $this->assertTrue($service->getActiveAlertsList() instanceof Collection);
    }

    public function test_devices_for_selection_are_ordered(): void
    {
        Device::factory()->create(['name' => 'Zeta']);
        Device::factory()->create(['name' => 'Alpha']);

        $service = app(DashboardMetricsService::class);
        $devices = $service->getDevicesForSelection();

        $this->assertEquals('Alpha', $devices->first()->name);
    }
}
