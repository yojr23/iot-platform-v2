<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthApiController;
use App\Http\Controllers\API\DeviceApiController;
use App\Http\Controllers\API\SensorApiController;
use App\Http\Controllers\Api\AlertFeedController;
use App\Http\Controllers\Api\InternalMetricsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\AlertRuleController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me'])->middleware('throttle:api-read');
        Route::post('/logout', [AuthApiController::class, 'logout'])->middleware('throttle:api-write');
    });
});

// API para ingestión IoT (sin sesión web, protegida por api_key del payload/header).
Route::get('/iot/sensors', [SensorApiController::class, 'iotIndex'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::post('/sensors/{sensor}/readings', [SensorApiController::class, 'storeReading'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-write');

// Endpoints públicos para visualización del dashboard sin sesión.
Route::get('/sensors/{sensor}/latest-readings', [SensorApiController::class, 'latestReadings'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');
Route::get('/devices/{device}/sensors', [DashboardController::class, 'getSensors'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');
Route::get('/alerts/active', [AlertFeedController::class, 'active'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::get('/internal/metrics/api-performance', [InternalMetricsController::class, 'apiPerformance'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('throttle:api-read');

    // API para dispositivos
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceApiController::class, 'index'])->middleware('throttle:api-read');
        Route::get('/{device}', [DeviceApiController::class, 'show'])->middleware('throttle:api-read');
        Route::post('/{device}/status', [DeviceApiController::class, 'updateStatus'])
            ->middleware(['admin', 'throttle:api-write']);
    });

    // API para sensores (lectura)
    Route::prefix('sensors')->group(function () {
        Route::get('/{sensor}/readings', [SensorApiController::class, 'readings'])->middleware('throttle:api-read');
        Route::get('/', [SensorApiController::class, 'index'])->middleware('throttle:api-read');
    });

    Route::get('/sensors/all/readings', [SensorController::class, 'getLatestReadings'])->middleware('throttle:api-read');

    // Ruta para reglas de alerta
    Route::prefix('alert-rules')->middleware('admin')->group(function () {
        Route::get('/create', [AlertRuleController::class, 'create'])->middleware('throttle:api-read');
        Route::get('/', [AlertRuleController::class, 'index'])->middleware('throttle:api-read');
        Route::post('/store', [AlertRuleController::class, 'store'])
            ->middleware('throttle:api-write')
            ->name('api.alert-rules.store');
        Route::delete('/{alertRule}', [AlertRuleController::class, 'destroy'])
            ->middleware('throttle:api-write');
    });

    // Ruta corregida: queda como /api/devices/{device}/sensor-list (sin doble prefijo /api/api)
    Route::get('/devices/{device}/sensor-list', [SensorController::class, 'getByDevice'])->middleware('throttle:api-read');
});
