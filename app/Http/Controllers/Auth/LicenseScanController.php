<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CampusId\LicenseOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LicenseScanController extends Controller
{
    public function __construct(
        private readonly LicenseOcrService $ocrService,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_license' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'driver_license.required' => 'Please capture or upload a driver\'s license photo to scan.',
            'driver_license.mimes' => 'Auto-scan supports JPG and PNG photos only.',
        ]);

        try {
            $result = $this->ocrService->scan($validated['driver_license']);
        } catch (Throwable $e) {
            report($e);

            $message = 'Unable to scan the license right now. Enter your details manually.';
            if (config('app.debug')) {
                $detail = trim($e->getMessage());
                if ($detail !== '') {
                    $message = $detail;
                }
            }

            return response()->json([
                'ok' => false,
                'message' => $message,
                'full_name' => null,
                'address' => null,
                'phone_number' => null,
                'driver_license_number' => null,
                'plate_number' => null,
                'warnings' => [],
            ], 422);
        }

        $status = ($result['ok'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }
}
