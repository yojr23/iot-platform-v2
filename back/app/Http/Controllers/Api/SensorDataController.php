<?php

namespace App\Http\Controllers\API;

use App\Models\Sensor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SensorDataController extends Controller
{
    public function __construct(private SensorApiController $sensorApiController)
    {
    }

    public function store(Request $request, Sensor $sensor)
    {
        // Wrapper de compatibilidad: centraliza la lógica de ingestión en SensorApiController.
        return $this->sensorApiController->storeReading($request, $sensor);
    }
}
