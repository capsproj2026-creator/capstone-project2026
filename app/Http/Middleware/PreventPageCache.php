<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\LoginController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (LoginController::noCacheHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
