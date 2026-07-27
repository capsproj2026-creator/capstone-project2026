<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailCheckController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:100'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address with a working domain (format and DNS check).',
        ]);

        $email = $validated['email'];

        return response()->json([
            'exists' => User::query()->where('email', $email)->exists(),
            'domain_valid' => true,
        ]);
    }
}
