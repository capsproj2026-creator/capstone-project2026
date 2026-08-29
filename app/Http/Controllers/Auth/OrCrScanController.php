<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CampusId\OrCrOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrCrScanController extends Controller
{
    public function __construct(
        private readonly OrCrOcrService $ocrService,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'kind' => ['required', 'in:or,cr'],
            'plate_number' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $result = $this->ocrService->scan(
                $validated['document'],
                $validated['kind'],
                $validated['plate_number'] ?? null
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => true,
                'kind' => $validated['kind'],
                'plate_number' => null,
                'warnings' => ['Could not auto-check this file. Review it manually before submitting.'],
                'message' => 'Could not auto-check this file. Review it manually before submitting.',
            ]);
        }

        return response()->json($result);
    }
}
