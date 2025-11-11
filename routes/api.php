<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DeviceApiController;
use App\Http\Controllers\API\SensorApiController;
use App\Http\Controllers\API\SensorDataController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\AlertRuleController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API para dispositivos
Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceApiController::class, 'index']);
    Route::get('/{device}', [DeviceApiController::class, 'show']);
    Route::post('/{device}/status', [DeviceApiController::class, 'updateStatus']);
});

// API para sensores
Route::prefix('sensors')->group(function () {
    Route::get('/{sensor}/latest-readings', [SensorApiController::class, 'latestReadings']);
    Route::get('/{sensor}/readings', [SensorApiController::class, 'readings']);
    Route::post('/{sensor}/readings', [SensorApiController::class, 'storeReading']);
    Route::get('/', [SensorApiController::class, 'index']);
});

Route::post('/sensors/{sensor}/readings', [SensorDataController::class, 'store'])
    ->name('api.sensors.readings.store');

Route::get('/devices/{device}/sensors', [DashboardController::class, 'getSensors']);
Route::get('/sensors/{sensor}/readings', [DashboardController::class, 'getSensorReadings']);
Route::get('/sensors/all/readings', [SensorController::class, 'getLatestReadings']);
Route::get('/alerts/active', [DashboardController::class, 'getActiveAlerts']);

// Ruta para reglas de alerta
Route::prefix('alert-rules')->group(function () {
    Route::get('/create', [AlertRuleController::class, 'create']);
    Route::post('/store', [AlertRuleController::class, 'store'])->name('api.alert-rules.store');
    Route::delete('/{alertRule}', [AlertRuleController::class, 'destroy']);
    Route::get('/', [AlertRuleController::class, 'index']);
});

Route::prefix('api')->group(function () {
    Route::get('/devices/{device}/sensor-list', [SensorController::class, 'getByDevice']);
});
