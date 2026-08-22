<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CampusId\CampusIdOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CampusIdScanController extends Controller
{
    public function __construct(
        private readonly CampusIdOcrService $ocrService,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'id_document.required' => 'Please choose a campus ID photo to scan.',
            'id_document.mimes' => 'Auto-scan supports JPG and PNG photos only.',
        ]);

        try {
            $result = $this->ocrService->scan($validated['id_document']);
        } catch (Throwable $e) {
            report($e);

            $message = 'Unable to scan the ID right now. Enter your details manually.';
            if (config('app.debug')) {
                $detail = trim($e->getMessage());
                if ($detail !== '') {
                    $message = $detail;
                }
            }

            return response()->json([
                'ok' => false,
                'message' => $message,
                'id_number' => null,
                'full_name' => null,
                'name_complete' => false,
                'warnings' => [],
            ], 422);
        }

        $status = ($result['ok'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }
}
