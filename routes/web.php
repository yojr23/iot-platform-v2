<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\EmailConfigController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SensorTypeController;
use App\Http\Controllers\DashboardPreferenceController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DeviceTypeController;
use App\Http\Controllers\UserRoleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Auth::routes();

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/profile', [App\Http\Controllers\HomeController::class, 'profile'])->name('profile')->middleware('auth');

Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::get('preferences', [DashboardPreferenceController::class, 'show'])->name('dashboard.preferences.show');
    Route::post('preferences', [DashboardPreferenceController::class, 'store'])->name('dashboard.preferences.store');
});

Route::middleware('auth')->group(function () {
    // Dispositivos
    Route::resource('devices', DeviceController::class);
    Route::post('devices/{device}/toggle-status', [DeviceController::class, 'toggleStatus'])->name('devices.toggle-status');
    Route::post('/devices/{device}/register-communication', [DeviceController::class, 'registerCommunication'])->name('devices.register-communication');

    // Sensores
    Route::resource('sensors', SensorController::class);
    Route::get('sensors/{sensor}/edit', [SensorController::class, 'edit'])->name('sensors.edit');
    Route::get('sensors/{sensor}/download', [SensorController::class, 'downloadReadings'])->name('sensors.download');
    Route::get('sensors/{sensor}/readings/filter', [SensorController::class, 'getReadingsByDateRange'])->name('sensors.readings.filter');

    // Alertas
    Route::post('alerts/mark-all-resolved', [AlertController::class, 'markAllAsResolved'])->name('alerts.mark-all-resolved');
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('alerts/unresolved', [AlertController::class, 'unresolved'])->name('alerts.unresolved');
    Route::put('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');

    // Configuración
    Route::get('config', [ConfigController::class, 'index'])->name('config.index');

    Route::middleware('admin')->group(function () {
        // Alert Rules
        Route::get('alert-rules/create', [AlertRuleController::class, 'create'])->name('alert-rules.create');
        Route::post('alert-rules', [AlertRuleController::class, 'store'])->name('alert-rules.store');
        Route::delete('alert-rules/{alertRule}', [AlertRuleController::class, 'destroy'])->name('alert-rules.destroy');

        // Tipos de Sensores
        Route::get('sensor-types/create', [SensorTypeController::class, 'create'])->name('sensor-types.create');
        Route::post('sensor-types', [SensorTypeController::class, 'store'])->name('sensor-types.store');
        Route::get('sensor-types/{sensorType}/edit', [SensorTypeController::class, 'edit'])->name('sensor-types.edit');
        Route::put('sensor-types/{sensorType}', [SensorTypeController::class, 'update'])->name('sensor-types.update');
        Route::delete('sensor-types/{sensorType}', [SensorTypeController::class, 'destroy'])->name('sensor-types.destroy');

        // Tipos de Dispositivos
        Route::get('device-types/create', [DeviceTypeController::class, 'create'])->name('device-types.create');
        Route::post('device-types', [DeviceTypeController::class, 'store'])->name('device-types.store');
        Route::get('device-types/{deviceType}/edit', [DeviceTypeController::class, 'edit'])->name('device-types.edit');
        Route::put('device-types/{deviceType}', [DeviceTypeController::class, 'update'])->name('device-types.update');
        Route::delete('device-types/{deviceType}', [DeviceTypeController::class, 'destroy'])->name('device-types.destroy');

        // Ubicaciones de Aulas
        Route::get('classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
        Route::post('classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::get('classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::delete('classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

        // Configuración
        Route::post('config', [ConfigController::class, 'update'])->name('config.update');
        Route::get('config/user-roles', [UserRoleController::class, 'index'])->name('config.user-roles.index');
        Route::patch('config/user-roles/{user}', [UserRoleController::class, 'update'])->name('config.user-roles.update');

        // Configuración de Email
        Route::get('email-config', [EmailConfigController::class, 'index'])->name('email-config.index');
        Route::put('email-config', [EmailConfigController::class, 'update'])->name('email-config.update');
        Route::post('email-config/test', [EmailConfigController::class, 'testEmail'])->name('email-config.test');
    });
});
