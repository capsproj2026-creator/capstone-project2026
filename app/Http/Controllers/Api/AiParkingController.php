<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingOccupancyService;
use App\Services\AiParkingViolationService;
use App\Support\PlateLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiParkingController extends Controller
{
    /**
     * YOLO AI service occupancy + events ingest.
     *
     * POST /api/ai-parking/occupancy
     * Header: X-AI-TOKEN
     */
    public function occupancy(Request $request, AiParkingOccupancyService $service, AiCameraRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['nullable', 'string', 'max:64'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_count' => ['required', 'integer', 'min:0', 'max:500'],
            'mode' => ['nullable', 'string', 'max:32'],
            'detections' => ['nullable', 'array', 'max:150'],
            'detections.*.class' => ['nullable', 'string', 'max:64'],
            'detections.*.vehicle_type' => ['nullable', 'string', 'max:64'],
            'detections.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'detections.*.plate' => ['nullable', 'string', 'max:32'],
            'detections.*.plate_status' => ['nullable', 'string', 'max:32'],
            'detections.*.ocr_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'detections.*.ocr_text' => ['nullable', 'string', 'max:32'],
            'detections.*.plate_text' => ['nullable', 'string', 'max:32'],
            'detections.*.has_plate_crop' => ['nullable', 'boolean'],
            'detections.*.track_id' => ['nullable', 'integer'],
            'detections.*.motion_state' => ['nullable', 'string', 'max:16'],
            'detections.*.motion_label' => ['nullable', 'string', 'max:32'],
            'detections.*.xyxy' => ['nullable', 'array', 'size:4'],
            'detections.*.xyxy.*' => ['numeric'],
            'detections.*.owner_name' => ['nullable', 'string', 'max:128'],
            'detections.*.owner_label' => ['nullable', 'string', 'max:128'],
            'detections.*.vehicle_details' => ['nullable', 'string', 'max:64'],
            'detections.*.department' => ['nullable', 'string', 'max:128'],
            'detections.*.registration_status' => ['nullable', 'string', 'max:64'],
            'detections.*.registered' => ['nullable', 'boolean'],
            'slots' => ['nullable', 'array', 'max:100'],
            'slots.*.slot_number' => ['required_with:slots', 'string', 'max:32'],
            'slots.*.occupied' => ['required_with:slots', 'boolean'],
            'events' => ['nullable', 'array', 'max:50'],
            'events.*.type' => ['required_with:events', 'string', 'max:64'],
            'events.*.zone_id' => ['nullable', 'string', 'max:128'],
            'events.*.track_id' => ['nullable', 'integer'],
            'events.*.plate' => ['nullable', 'string', 'max:32'],
            'events.*.plate_status' => ['nullable', 'string', 'max:32'],
            'events.*.ocr_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.confidence' => ['nullable', 'numeric'],
            'events.*.label' => ['nullable', 'string', 'max:128'],
            'events.*.dwell_minutes' => ['nullable', 'numeric'],
            'events.*.slots' => ['nullable', 'array'],
            'events.*.vehicles_in_slot' => ['nullable', 'integer'],
            'events.*.evidence_jpeg_base64' => ['nullable', 'string', 'max:800000'],
            'events.*.camera_id' => ['nullable', 'string', 'max:64'],
            'events.*.area_id' => ['nullable', 'integer'],
            'events.*.vehicle_details' => ['nullable', 'string', 'max:64'],
        ]);

        $cameraId = (string) ($validated['camera_id'] ?? $registry->primaryCameraId());
        // Area is resolved from server camera registry — never trust client area_id alone.
        $areaId = $registry->resolveAreaId($cameraId, isset($validated['area_id']) ? (int) $validated['area_id'] : null);

        $snapshot = $service->applyOccupancy(
            $areaId,
            (int) $validated['vehicle_count'],
            $cameraId,
            $validated['detections'] ?? [],
            $validated['slots'] ?? null,
            $validated['events'] ?? [],
            (string) ($validated['mode'] ?? 'count'),
            $request->exists('detections')
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
    public function events(Request $request, AiParkingOccupancyService $service, AiCameraRegistry $registry): JsonResponse
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
            'events.*.evidence_jpeg_base64' => ['nullable', 'string', 'max:800000'],
            'events.*.vehicle_details' => ['nullable', 'string', 'max:64'],
            'events.*.area_id' => ['nullable', 'integer'],
        ]);

        $cameraId = (string) ($validated['camera_id'] ?? $registry->primaryCameraId());
        $results = app(AiParkingViolationService::class)
            ->processEvents($validated['events'], $cameraId);

        $latest = $service->latestSnapshot($cameraId) ?? [
            'camera_id' => $cameraId,
            'area_id' => $registry->resolveAreaId($cameraId),
            'events' => [],
        ];
        $latest['events'] = array_values(array_slice(
            array_merge($latest['events'] ?? [], $validated['events']),
            -20
        ));
        $latest['violation_results'] = $results;
        $latest['updated_at'] = now()->toIso8601String();
        $latest['updated_at_label'] = now()->format('h:i:s A');

        $ttl = now()->addMinutes(30);
        Cache::put($service->cacheKeyForCamera($cameraId), $latest, $ttl);
        if (strcasecmp($cameraId, $registry->primaryCameraId()) === 0) {
            Cache::put(AiParkingOccupancyService::CACHE_KEY, $latest, $ttl);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Events processed.',
            'data' => [
                'violation_results' => $results,
                'ai' => $latest,
            ],
        ]);
    }

    /**
     * Lightweight plate → owner lookup for the Python AI service (overlay cache).
     * POST /api/ai-parking/plate-lookup
     */
    public function plateLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:32'],
        ]);

        $identity = PlateLookup::identity($validated['plate']);
        $identity['owner_role'] = $identity['role'] ?? null;

        return response()->json([
            'status' => 'ok',
            'data' => $identity,
        ]);
    }
}
