<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Sensor;
use App\Services\Monitoring\RuleToGraphZones;
use Illuminate\Http\JsonResponse;

class DashboardGraphCatalogController extends Controller
{
    public function index(RuleToGraphZones $zones): JsonResponse
    {
        $devices = Device::query()
            ->select(['id', 'name'])
            ->with([
                'sensors' => fn ($query) => $query
                    ->select(['id', 'device_id', 'sensor_type_id', 'name'])
                    ->orderBy('id'),
                'sensors.sensorType:id,unit',
            ])
            ->orderBy('id')
            ->get();

        $sensors = $devices->flatMap->sensors;
        $projections = $zones->zonesForMany($sensors);

        return response()->json([
            'version' => 1,
            'default_sensor_id' => $sensors->first()?->id,
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'sensors' => $device->sensors->map(fn (Sensor $sensor) => [
                    'id' => $sensor->id,
                    'name' => $sensor->name,
                    'unit' => $sensor->sensorType?->unit,
                    ...$this->graphZones($projections[$sensor->id]),
                ])->values(),
            ])->values(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @return array{bands: list<array{from: float|null, to: float|null, severity: string}>, boundaries: list<array{value: float, severity: string, bound: string}>}
     */
    private function graphZones(array $projection): array
    {
        return [
            'bands' => array_map(
                fn (array $zone) => ['from' => $zone['from'], 'to' => $zone['to'], 'severity' => $zone['severity']],
                $projection['zones'],
            ),
            'boundaries' => array_map(
                fn (array $boundary) => [
                    'value' => $boundary['value'],
                    'severity' => $boundary['severity'],
                    'bound' => $boundary['bound'],
                ],
                $projection['boundaries'],
            ),
        ];
    }
}
