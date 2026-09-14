<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Accepts either the legacy is_admin column or the new RBAC admin/superadmin role.
     * During the transition period both paths are supported so legacy web.php routes
     * keep working while API routes already use can:middleware.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $user = Auth::user();
        $userId = $user?->id;

        $isAdmin = $user && (
            $user->is_admin
            || $user->hasAnyRole(['admin', 'superadmin'])
        );

        Log::info('EnsureUserIsAdmin: checking', [
            'user_id' => $userId,
            'is_admin' => $isAdmin,
        ]);

        if (! Auth::check() || ! $isAdmin) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::warning('EnsureUserIsAdmin: denied', [
                'user_id' => $userId,
                'is_admin' => $isAdmin,
                'duration_ms' => $durationMs,
            ]);
            abort(403, 'Esta acción requiere permisos de administrador.');
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EnsureUserIsAdmin: passed', [
            'user_id' => $userId,
            'duration_ms' => $durationMs,
        ]);

        return $next($request);
    }
}
