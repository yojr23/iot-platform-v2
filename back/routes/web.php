<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| BACK-01 (PLAN Mac M3): the Vue SPA (front/) is the SOLE canonical product
| dashboard and the SOLE product mutation owner (all writes go through the
| JSON API -> application/domain services). Legacy Blade product CRUD used to
| mutate models directly, bypassing canonical services — that dual-ownership
| surface is retired here. Blade now serves only auth/verify/profile pages
| that are genuinely Laravel-rendered; every product read redirects to the SPA
| and no product write route remains. See audit.md / PLAN.md Stage 6.0 + M3.
|
*/

// Redirect the SPA product surface to the Vue app.
$spa = fn (string $path = '') => redirect()->away(rtrim(config('app.front_url'), '/').$path);

Route::get('/', fn () => $spa('/dashboard'));

Auth::routes(['verify' => true]);

// SEC-PASS-001 / SEC-ENUM-001: throttle account-recovery endpoints so weak
// passwords/enumeration can't be brute-forced. `auth-login` already covers
// the login route itself; these are the remaining unthrottled recovery paths.
// Route::getRoutes()->getByName() reads a name-lookup cache that is only
// rebuilt after all route files finish loading, so it can't see these routes
// yet at this point in web.php. Route::getName() reads the route's own
// fluently-assigned name directly and is safe to check here instead.
$recoveryRouteNames = ['password.email', 'password.update', 'verification.resend'];
foreach (Route::getRoutes()->get() as $route) {
    if (in_array($route->getName(), $recoveryRouteNames, true)) {
        $route->middleware('throttle:6,1');
    }
}

Route::get('/dashboard', fn () => $spa('/dashboard'))->name('dashboard');

// Profile is a genuinely Laravel-rendered authenticated page (no product
// mutation), so it stays in Blade.
Route::get('/profile', [HomeController::class, 'profile'])
    ->name('profile')
    ->middleware(['auth', 'verified']);

// Product reads: keep the named routes (deep links / bookmarks / mailers) but
// hand them to the canonical SPA. No product WRITE route exists anymore — those
// live only on the JSON API (routes/api.php) behind the canonical services.
Route::middleware(['auth', 'verified'])->group(function () use ($spa) {
    Route::get('metrics', fn () => $spa('/metrics'))->name('metrics.index');

    Route::get('devices', fn () => $spa('/devices'))->name('devices.index');
    Route::get('devices/create', fn () => $spa('/devices'))->name('devices.create');
    Route::get('devices/{device}', fn ($device) => $spa('/devices/'.$device))->name('devices.show');
    Route::get('devices/{device}/edit', fn ($device) => $spa('/devices/'.$device))->name('devices.edit');

    Route::get('sensors', fn () => $spa('/sensors'))->name('sensors.index');
    Route::get('sensors/create', fn () => $spa('/sensors'))->name('sensors.create');
    Route::get('sensors/{sensor}', fn ($sensor) => $spa('/sensors/'.$sensor))->name('sensors.show');
    Route::get('sensors/{sensor}/edit', fn ($sensor) => $spa('/sensors/'.$sensor))->name('sensors.edit');

    Route::get('alerts', fn () => $spa('/alerts'))->name('alerts.index');
    Route::get('alerts/unresolved', fn () => $spa('/alerts'))->name('alerts.unresolved');

    Route::middleware('admin')->group(function () use ($spa) {
        Route::get('config', fn () => $spa('/config'))->name('config.index');
        Route::get('config/user-roles', fn () => $spa('/users'))->name('config.user-roles.index');
        Route::get('email-config', fn () => $spa('/config/email'))->name('email-config.index');

        Route::get('alert-rules/create', fn () => $spa('/alert-rules'))->name('alert-rules.create');
        Route::get('sensor-types/create', fn () => $spa('/sensor-types'))->name('sensor-types.create');
        Route::get('sensor-types/{sensorType}/edit', fn ($sensorType) => $spa('/sensor-types'))->name('sensor-types.edit');
        Route::get('device-types/create', fn () => $spa('/device-types'))->name('device-types.create');
        Route::get('device-types/{deviceType}/edit', fn ($deviceType) => $spa('/device-types'))->name('device-types.edit');
        Route::get('labs/create', fn () => $spa('/labs'))->name('labs.create');
        Route::get('labs/{lab}/edit', fn ($lab) => $spa('/labs'))->name('labs.edit');
    });
});
