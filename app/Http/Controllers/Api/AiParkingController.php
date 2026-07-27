<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiParkingController extends Controller
{
    /**
     * YOLO AI service occupancy + events ingest.
     *
     * POST /api/ai-parking/occupancy
     * Header: X-AI-TOKEN
     * Body: {
     *   camera_id, area_id, vehicle_count,
     *   detections?, slots?: [{slot_number, occupied}],
     *   events?: [{type, zone_id, track_id, plate, ...}],
     *   mode?
     * }
     */
    public function occupancy(Request $request, AiParkingOccupancyService $service): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['nullable', 'string', 'max:64'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_count' => ['required', 'integer', 'min:0', 'max:500'],
            'mode' => ['nullable', 'string', 'max:32'],
            'detections' => ['nullable', 'array', 'max:100'],
            'detections.*.class' => ['nullable', 'string', 'max:64'],
            'detections.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'detections.*.plate' => ['nullable', 'string', 'max:32'],
            'detections.*.track_id' => ['nullable', 'integer'],
            'slots' => ['nullable', 'array', 'max:100'],
            'slots.*.slot_number' => ['required_with:slots', 'string', 'max:32'],
            'slots.*.occupied' => ['required_with:slots', 'boolean'],
            'events' => ['nullable', 'array', 'max:50'],
            'events.*.type' => ['required_with:events', 'string', 'max:64'],
            'events.*.zone_id' => ['nullable', 'string', 'max:128'],
            'events.*.track_id' => ['nullable', 'integer'],
            'events.*.plate' => ['nullable', 'string', 'max:32'],
            'events.*.confidence' => ['nullable', 'numeric'],
            'events.*.label' => ['nullable', 'string', 'max:128'],
            'events.*.dwell_minutes' => ['nullable', 'numeric'],
            'events.*.slots' => ['nullable', 'array'],
            'events.*.vehicles_in_slot' => ['nullable', 'integer'],
        ]);

        // Never trust client area_id for occupancy writes — pin to configured AI lot.
        $areaId = $service->monitoredAreaId();
        $snapshot = $service->applyOccupancy(
            $areaId,
            (int) $validated['vehicle_count'],
            (string) ($validated['camera_id'] ?? 'CAM-AI-1'),
            $validated['detections'] ?? [],
            $validated['slots'] ?? null,
            $validated['events'] ?? [],
            (string) ($validated['mode'] ?? 'count')
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Occupancy updated.',
            'data' => $snapshot,
        ]);
    }

    /**
     * Optional dedicated events endpoint (same auth). Does not rewrite slot occupancy.
     * POST /api/ai-parking/events
     */
    public function events(Request $request, AiParkingOccupancyService $service): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['nullable', 'string', 'max:64'],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.type' => ['required', 'string', 'max:64'],
            'events.*.zone_id' => ['nullable', 'string', 'max:128'],
            'events.*.track_id' => ['nullable', 'integer'],
            'events.*.plate' => ['nullable', 'string', 'max:32'],
            'events.*.confidence' => ['nullable', 'numeric'],
            'events.*.label' => ['nullable', 'string', 'max:128'],
            'events.*.dwell_minutes' => ['nullable', 'numeric'],
            'events.*.slots' => ['nullable', 'array'],
        ]);

        $cameraId = (string) ($validated['camera_id'] ?? 'CAM-AI-1');
        $results = app(\App\Services\AiParkingViolationService::class)
            ->processEvents($validated['events'], $cameraId);

        $latest = $service->latestSnapshot() ?? [];
        $latest['events'] = array_values(array_slice(
            array_merge($latest['events'] ?? [], $validated['events']),
            -20
        ));
        $latest['violation_results'] = $results;
        $latest['updated_at'] = now()->toIso8601String();
        $latest['updated_at_label'] = now()->format('h:i:s A');
        \Illuminate\Support\Facades\Cache::put(
            AiParkingOccupancyService::CACHE_KEY,
            $latest,
            now()->addMinutes(30)
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Events processed.',
            'data' => [
                'violation_results' => $results,
                'ai' => $latest,
            ],
        ]);
    }
}
