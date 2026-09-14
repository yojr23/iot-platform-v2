<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $startTime = microtime(true);
        $user = Auth::user();
        $userId = $user?->id;

        Log::info('EnsureUserHasPermission: checking', [
            'user_id' => $userId,
            'permissions' => $permissions,
        ]);

        if (! Auth::check()) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::warning('EnsureUserHasPermission: denied (unauthenticated)', [
                'user_id' => $userId,
                'duration_ms' => $durationMs,
            ]);
            abort(401, 'Se requiere autenticación.');
        }

        // Check if user has ANY of the required permissions
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (! $hasPermission) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::warning('EnsureUserHasPermission: denied', [
                'user_id' => $userId,
                'permissions' => $permissions,
                'duration_ms' => $durationMs,
            ]);
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EnsureUserHasPermission: passed', [
            'user_id' => $userId,
            'permissions' => $permissions,
            'duration_ms' => $durationMs,
        ]);

        return $next($request);
    }
}
