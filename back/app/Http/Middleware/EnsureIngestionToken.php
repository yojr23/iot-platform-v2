<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIngestionToken
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = (string) config('app.ingestion_service_token', '');
        $providedToken = (string) $request->header('X-Ingestion-Token', '');

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid ingestion token.',
            ], 401);
        }

        return $next($request);
    }
}
