<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyServiceSignature
{
    /**
     * Handle internal inter-service requests (Vanilla PHP <-> Laravel Core)
     * using a shared internal secret key header (X-Internal-Service-Key)
     * or standard Sanctum Bearer token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = env('INTERNAL_SERVICE_SECRET', 'santafe_internal_bridge_secret_key_2026');
        $providedKey = $request->header('X-Internal-Service-Key');

        // Check if matching internal service key is provided
        if ($providedKey && hash_equals($expectedKey, $providedKey)) {
            return $next($request);
        }

        // Check if authenticated via Sanctum
        if ($request->user()) {
            return $next($request);
        }

        // If Authorization Bearer token is sent, allow sanctum middleware to validate
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized service request. Missing or invalid authentication credentials.',
        ], 401);
    }
}
