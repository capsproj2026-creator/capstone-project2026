<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAiParkingApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ai_parking.api_token', '');

        if ($expected === '') {
            return response()->json([
                'status' => 'error',
                'code' => 'misconfigured',
                'message' => 'AI_PARKING_API_TOKEN is not configured on the server.',
            ], 503);
        }

        // Header-only — never accept token from query/body (leaks via logs/Referer).
        $provided = (string) (
            $request->header('X-AI-TOKEN')
            ?? $request->bearerToken()
            ?? ''
        );

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'status' => 'error',
                'code' => 'unauthorized',
                'message' => 'Invalid AI parking API token.',
            ], 401);
        }

        return $next($request);
    }
}
