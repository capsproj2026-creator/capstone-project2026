<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Support\PlateLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlateLookupController extends Controller
{
    public function index(Request $request): View
    {
        $plate = (string) $request->input('plate', $request->query('plate', ''));
        $result = null;

        if (filled($plate)) {
            PlateLookup::forgetIndex();
            $result = PlateLookup::identity($plate);
        }

        return view('guard.plate-lookup', [
            'plate' => $plate,
            'result' => $result,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:32'],
        ]);

        PlateLookup::forgetIndex();

        return response()->json([
            'status' => 'ok',
            'data' => PlateLookup::identity($validated['plate']),
        ]);
    }
}
