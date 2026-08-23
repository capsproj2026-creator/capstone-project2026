<?php

namespace App\Http\Middleware;

use App\Support\VisitorPreRegister;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyVisitorPreRegisterWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = VisitorPreRegister::webhookToken();

        if ($expected === '') {
            return response()->json([
                'ok' => false,
                'message' => 'VISITOR_PRE_REGISTER_WEBHOOK_TOKEN is not configured on the server.',
            ], 503);
        }

        $provided = (string) (
            $request->header('X-VISITOR-PRE-REGISTER-TOKEN')
            ?? $request->bearerToken()
            ?? ''
        );

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid visitor pre-register webhook token.',
            ], 401);
        }

        return $next($request);
    }
}
