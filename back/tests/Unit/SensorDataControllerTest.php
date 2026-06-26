<?php

namespace Tests\Unit;

use App\Http\Controllers\API\SensorApiController;
use App\Http\Controllers\API\SensorDataController;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SensorDataController::class)]
class SensorDataControllerTest extends TestCase
{
    public function test_store_forwards_request_to_sensor_api_controller(): void
    {
        $request = Request::create('/api/sensors/1/readings', 'POST', [
            'value' => 10,
            'api_key' => 'test-key',
        ]);

        $sensor = Mockery::mock(Sensor::class);

        $expectedResponse = new JsonResponse([
            'message' => 'proxied',
        ], 201);

        $apiController = Mockery::mock(SensorApiController::class);
        $apiController
            ->shouldReceive('storeReading')
            ->once()
            ->with($request, $sensor)
            ->andReturn($expectedResponse);

        $controller = new SensorDataController($apiController);

        $response = $controller->store($request, $sensor);

        $this->assertSame($expectedResponse, $response);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
