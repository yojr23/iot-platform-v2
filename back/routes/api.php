<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthApiController;
use App\Http\Controllers\API\DeviceApiController;
use App\Http\Controllers\API\SensorApiController;
use App\Http\Controllers\Api\AlertController as ApiAlertController;
use App\Http\Controllers\Api\AlertRuleController as ApiAlertRuleController;
use App\Http\Controllers\Api\ConfigController as ApiConfigController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DashboardPreferenceController as ApiDashboardPreferenceController;
use App\Http\Controllers\Api\DeviceTypeController as ApiDeviceTypeController;
use App\Http\Controllers\Api\EmailConfigController as ApiEmailConfigController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\InternalMetricsController;
use App\Http\Controllers\Api\LabController as ApiLabController;
use App\Http\Controllers\Api\MetricsController as ApiMetricsController;
use App\Http\Controllers\Api\ProfileController as ApiProfileController;
use App\Http\Controllers\Api\SensorTypeController as ApiSensorTypeController;
use App\Http\Controllers\Api\UserRoleController as ApiUserRoleController;

Route::get('/health', [HealthController::class, 'show'])->middleware('throttle:api-read');
Route::get('/config/public', [ApiConfigController::class, 'publicConfig'])->middleware('throttle:api-read');
Route::get('/dashboard/public', [ApiDashboardController::class, 'publicData'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/register', [AuthApiController::class, 'register'])->middleware('throttle:auth-login');
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:auth-login');
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:auth-login');
    Route::get('/verify-email/{id}/{hash}', [AuthApiController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:api-read'])
        ->name('api.auth.verify-email');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me'])->middleware('throttle:api-read');
        Route::post('/logout', [AuthApiController::class, 'logout'])->middleware('throttle:api-write');
        Route::post('/resend-verification', [AuthApiController::class, 'resendVerificationEmail'])
            ->middleware('throttle:api-write');
    });
});

// API para ingestión IoT (sin sesión web, protegida por api_key del payload/header).
Route::get('/iot/sensors', [SensorApiController::class, 'iotIndex'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::post('/sensors/{sensor}/readings', [SensorApiController::class, 'storeReading'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-write');

Route::post('/ingestion/events', [IngestionController::class, 'store'])
    ->middleware('ingestion.token')
    ->middleware('api.metrics')
    ->middleware('throttle:api-write');

// Endpoints públicos para visualización del dashboard sin sesión.
Route::get('/sensors/{sensor}/latest-readings', [SensorApiController::class, 'latestReadings'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');
Route::get('/devices/{device}/sensors', [DeviceApiController::class, 'sensors'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');
Route::get('/alerts/active', [ApiAlertController::class, 'active'])
    ->middleware('api.metrics')
    ->middleware('throttle:api-read');

Route::get('/internal/metrics/api-performance', [InternalMetricsController::class, 'apiPerformance'])
    ->middleware('api.metrics')
    ->middleware('auth:sanctum')
    ->middleware('admin')
    ->middleware('throttle:api-read');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('throttle:api-read');

    Route::get('/profile', [ApiProfileController::class, 'show'])->middleware('throttle:api-read');

    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics', [ApiDashboardController::class, 'metrics'])->middleware('throttle:api-read');
        Route::get('/preferences', [ApiDashboardPreferenceController::class, 'show'])->middleware('throttle:api-read');
        Route::put('/preferences', [ApiDashboardPreferenceController::class, 'store'])->middleware('throttle:api-write');
    });

    // API para dispositivos
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceApiController::class, 'index'])->middleware('throttle:api-read');
        Route::post('/', [DeviceApiController::class, 'store'])->middleware(['admin', 'throttle:api-write']);
        Route::get('/{device}', [DeviceApiController::class, 'show'])->middleware('throttle:api-read');
        Route::put('/{device}', [DeviceApiController::class, 'update'])->middleware(['admin', 'throttle:api-write']);
        Route::delete('/{device}', [DeviceApiController::class, 'destroy'])->middleware(['admin', 'throttle:api-write']);
        Route::post('/{device}/status', [DeviceApiController::class, 'updateStatus'])
            ->middleware(['admin', 'throttle:api-write']);
    });

    // API para sensores
    Route::prefix('sensors')->group(function () {
        Route::get('/all/readings', [SensorApiController::class, 'allReadings'])->middleware('throttle:api-read');
        Route::post('/', [SensorApiController::class, 'createSensor'])->middleware(['admin', 'throttle:api-write']);
        Route::get('/{sensor}/readings/export', [SensorApiController::class, 'exportReadings'])->middleware('throttle:api-read');
        Route::get('/{sensor}/readings', [SensorApiController::class, 'readings'])->middleware('throttle:api-read');
        Route::get('/{sensor}', [SensorApiController::class, 'show'])->middleware('throttle:api-read');
        Route::put('/{sensor}', [SensorApiController::class, 'updateSensor'])->middleware(['admin', 'throttle:api-write']);
        Route::delete('/{sensor}', [SensorApiController::class, 'destroySensor'])->middleware(['admin', 'throttle:api-write']);
        Route::get('/', [SensorApiController::class, 'index'])->middleware('throttle:api-read');
    });

    Route::prefix('alerts')->group(function () {
        Route::get('/', [ApiAlertController::class, 'index'])->middleware('throttle:api-read');
        Route::get('/unresolved', [ApiAlertController::class, 'unresolved'])->middleware('throttle:api-read');
        Route::post('/resolve-all', [ApiAlertController::class, 'resolveAll'])->middleware('throttle:api-write');
        Route::get('/{alert}', [ApiAlertController::class, 'show'])->middleware('throttle:api-read');
        Route::patch('/{alert}/resolve', [ApiAlertController::class, 'resolve'])->middleware('throttle:api-write');
    });

    Route::middleware('admin')->group(function () {
        Route::get('/metrics', [ApiMetricsController::class, 'index'])->middleware('throttle:api-read');
        Route::get('/users', [ApiUserRoleController::class, 'index'])->middleware('throttle:api-read');
        Route::patch('/users/{user}/role', [ApiUserRoleController::class, 'update'])->middleware('throttle:api-write');

        Route::apiResource('labs', ApiLabController::class)->except(['create', 'edit']);
        Route::apiResource('sensor-types', ApiSensorTypeController::class)
            ->parameters(['sensor-types' => 'sensorType'])
            ->except(['create', 'edit']);
        Route::apiResource('device-types', ApiDeviceTypeController::class)
            ->parameters(['device-types' => 'deviceType'])
            ->except(['create', 'edit']);
    });

    Route::prefix('config')->middleware('admin')->group(function () {
        Route::get('/alerts', [ApiConfigController::class, 'alerts'])->middleware('throttle:api-read');
        Route::put('/alerts', [ApiConfigController::class, 'updateAlerts'])->middleware('throttle:api-write');
        Route::get('/email', [ApiEmailConfigController::class, 'show'])->middleware('throttle:api-read');
        Route::put('/email', [ApiEmailConfigController::class, 'update'])->middleware('throttle:api-write');
        Route::post('/email/test', [ApiEmailConfigController::class, 'test'])->middleware('throttle:api-write');
    });

    // Ruta para reglas de alerta
    Route::prefix('alert-rules')->middleware('admin')->group(function () {
        Route::get('/create', [ApiAlertRuleController::class, 'create'])->middleware('throttle:api-read');
        Route::get('/', [ApiAlertRuleController::class, 'index'])->middleware('throttle:api-read');
        Route::post('/', [ApiAlertRuleController::class, 'store'])->middleware('throttle:api-write');
        Route::post('/store', [ApiAlertRuleController::class, 'store'])
            ->middleware('throttle:api-write')
            ->name('api.alert-rules.store');
        Route::get('/{alertRule}', [ApiAlertRuleController::class, 'show'])->middleware('throttle:api-read');
        Route::put('/{alertRule}', [ApiAlertRuleController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('/{alertRule}', [ApiAlertRuleController::class, 'destroy'])->middleware('throttle:api-write');
    });

    // Ruta corregida: queda como /api/devices/{device}/sensor-list (sin doble prefijo /api/api)
    Route::get('/devices/{device}/sensor-list', [DeviceApiController::class, 'sensors'])->middleware('throttle:api-read');
});
