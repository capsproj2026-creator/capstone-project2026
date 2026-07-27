<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RfidAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidGateController extends Controller
{
    /**
     * ESP32 RFID gate endpoint.
     *
     * POST /api/rfid/scan
     * Body: { "uid": "...", "gate_id": "GATE-IN-1", "direction": "Entry"|"Exit" }
     * Header: X-RFID-TOKEN: <RFID_API_TOKEN>
     */
    public function scan(Request $request, RfidAccessService $rfid): JsonResponse
    {
        $validated = $request->validate([
            'uid' => ['required', 'string', 'min:4', 'max:64'],
            'gate_id' => ['required', 'string', 'max:64'],
            'direction' => ['required', 'string', 'in:Entry,Exit,entry,exit,ENTRY,EXIT'],
        ]);

        $result = $rfid->process(
            $validated['uid'],
            $validated['gate_id'],
            $validated['direction']
        );

        $httpStatus = $result['granted'] ? 200 : 403;

        if ($result['code'] === 'card_not_registered') {
            $httpStatus = 404;
        }

        if (in_array($result['code'], ['already_inside', 'already_outside'], true)) {
            $httpStatus = 409;
        }

        return response()->json($result, $httpStatus);
    }
}
