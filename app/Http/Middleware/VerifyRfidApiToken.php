<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRfidApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.rfid.api_token', '');

        if ($expected === '') {
            return response()->json([
                'status' => 'Access Denied',
                'code' => 'misconfigured',
                'granted' => false,
                'message' => 'RFID_API_TOKEN is not configured on the server.',
            ], 503);
        }

        // Header-only — never accept token from query/body (leaks via logs/Referer).
        $provided = (string) (
            $request->header('X-RFID-TOKEN')
            ?? $request->bearerToken()
            ?? ''
        );

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'status' => 'Access Denied',
                'code' => 'unauthorized',
                'granted' => false,
                'message' => 'Invalid RFID API token.',
            ], 401);
        }

        return $next($request);
    }
}
