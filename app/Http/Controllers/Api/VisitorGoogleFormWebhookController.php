<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisitorService;
use App\Support\VisitorPreRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VisitorGoogleFormWebhookController extends Controller
{
    public function store(Request $request, VisitorService $visitors): JsonResponse
    {
        $validator = Validator::make($request->all(), VisitorPreRegister::validationRules());

        $validator->after(function ($validator) use ($request): void {
            $vehicleId = $request->input('vehicle_id');
            $vehicleName = trim((string) $request->input('vehicle_name', ''));

            if (! filled($vehicleId) && $vehicleName === '') {
                $validator->errors()->add('vehicle_id', 'Provide vehicle_id or vehicle_name.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $payload = VisitorPreRegister::payloadForService($validated);

        $visitor = $visitors->preRegister($payload);

        return response()->json([
            'ok' => true,
            'visitor_id' => $visitor->id,
            'confirmation_code' => $visitor->confirmation_code,
            'success_url' => VisitorPreRegister::signedSuccessUrl((int) $visitor->id),
        ]);
    }
}
