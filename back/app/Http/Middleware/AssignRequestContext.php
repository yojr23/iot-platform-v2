<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestContext
{
    private const REQUEST_ID_HEADER = 'X-Request-Id';

    private const CANONICAL_UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->effectiveRequestId($request->header(self::REQUEST_ID_HEADER));

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        return $response;
    }

    private function effectiveRequestId(?string $candidate): string
    {
        if (
            $candidate !== null
            && preg_match(self::CANONICAL_UUID_PATTERN, $candidate) === 1
        ) {
            return strtolower($candidate);
        }

        return (string) Str::uuid7();
    }
}
