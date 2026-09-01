<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveCameraController extends Controller
{
    public function index(
        AiParkingOccupancyService $ai,
        AiParkingHealthService $health,
        AiCameraRegistry $registry
    ): View {
        $routeName = request()->route()?->getName() ?? '';
        $isGuard = str_contains($routeName, 'guard.');
        $layout = $isGuard ? 'layouts.guard' : 'layouts.portal';

        $cameras = [];
        $areaIds = collect($registry->cameras())->pluck('area_id')->filter()->unique()->values()->all();
        $areasById = $areaIds === []
            ? collect()
            : ParkingArea::query()->whereIn('id', $areaIds)->get()->keyBy('id');

        foreach ($registry->cameras() as $cam) {
            // Do not probe MJPEG here — that blocked Live Cameras for 60s+.
            // Clean MJPEG for Live Cameras (no YOLO overlay).
            $streamUrl = $health->streamBrowserUrl($cam['id'], false) ?? ($cam['stream_url'] ?? null);
            $snap = $ai->latestSnapshot($cam['id']);
            $area = $areasById->get($cam['area_id']);
            $parkingUrl = $isGuard
                ? route('guard.parking', ['zone_id' => $cam['area_id']])
                : route('admin.parking', ['zone_id' => $cam['area_id']]);

            $hasStream = filled($streamUrl);
            $ingestActive = $health->isIngestActive($cam['id']);

            $cameras[] = [
                'id' => strtolower(str_replace('_', '-', $cam['id'])),
                'camera_id' => $cam['id'],
                'name' => $area?->area_name ?? $cam['name'],
                'location' => $cam['location'],
                'stream_url' => $streamUrl,
                'online' => $hasStream,
                'ai_monitored' => false,
                'parking_url' => $parkingUrl,
                'vehicle_count' => $snap['vehicle_count'] ?? null,
                'occupied' => $snap['occupied'] ?? null,
                'available' => $snap['available'] ?? null,
                'updated_at_label' => $snap['updated_at_label'] ?? null,
                'ingest_active' => $ingestActive,
            ];
        }

        // Only show configured AI CCTV feeds on Live Cameras (no fake offline placeholders).

        $primary = $registry->primaryCameraId();
        $areaId = $registry->resolveAreaId($primary);
        $area = ParkingArea::query()->find($areaId);
        $streamUrl = $health->streamBrowserUrl($primary);
        $aiHealth = $health->statusFast($isGuard, $primary);

        $total = count($cameras);
        $online = collect($cameras)->where('online', true)->count();

        return view('cameras.live', [
            'layout' => $layout,
            'streamUrl' => $streamUrl,
            'ai' => $ai->latestSnapshot($primary),
            'aiCameras' => $ai->allSnapshots(),
            'aiHealth' => $aiHealth,
            'aiCamerasHealth' => $health->statusAll($isGuard, false),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'Parking area',
            'cameras' => $cameras,
            'cameraStats' => [
                'total' => $total,
                'online' => $online,
                'offline' => $total - $online,
            ],
            'statusUrl' => $isGuard
                ? route('guard.parking.status')
                : route('admin.parking.status'),
            'parkingUrl' => $isGuard
                ? route('guard.parking', ['zone_id' => $areaId])
                : route('admin.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function aiMonitor(
        AiParkingOccupancyService $ai,
        AiParkingHealthService $health,
        AiCameraRegistry $registry
    ): View {
        $primary = $registry->primaryCameraId();
        $areaId = $registry->resolveAreaId($primary);
        $area = ParkingArea::query()->find($areaId);
        $aiHealth = $health->statusFast(true, $primary);

        return view('guard.ai-parking-monitor', [
            'streamUrl' => $health->streamBrowserUrl($primary, true),
            'ai' => $ai->latestSnapshot($primary),
            'aiHealth' => $aiHealth,
            'aiCameras' => $ai->allSnapshots(),
            'aiCamerasHealth' => $health->statusAll(true, false),
            'registryCameras' => $registry->cameras(),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'Parking area',
            'statusUrl' => route('guard.parking.status'),
            'correctPlateUrl' => route('guard.ai-parking.correct-plate'),
            'parkingUrl' => route('guard.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function stream(AiParkingHealthService $health, ?string $camera = null): StreamedResponse|Response
    {
        $withAi = request()->boolean('ai', true);
        $upstream = $health->upstreamStreamUrl($camera, $withAi);
        if ($upstream === null) {
            abort(503, 'AI parking stream is not configured.');
        }

        // SSRF guard: only proxy to the local AI parking MJPEG service.
        $host = parse_url($upstream, PHP_URL_HOST);
        if (! in_array(strtolower((string) $host), ['127.0.0.1', 'localhost', '::1'], true)) {
            abort(503, 'AI parking stream host is not allowed.');
        }

        try {
            $response = Http::timeout(5)
                ->withOptions(['stream' => true, 'read_timeout' => 300])
                ->get($upstream);
        } catch (\Throwable) {
            abort(503, 'AI parking stream is unreachable.');
        }

        if (! $response->successful()) {
            abort(503, 'AI parking stream returned an error.');
        }

        $contentType = $response->header('Content-Type') ?: 'multipart/x-mixed-replace; boundary=frame';

        return response()->stream(function () use ($response) {
            $body = $response->toPsrResponse()->getBody();

            try {
                while (! $body->eof()) {
                    if (connection_aborted()) {
                        break;
                    }

                    echo $body->read(8192);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }
            } finally {
                $body->close();
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function status(AiParkingOccupancyService $ai): JsonResponse
    {
        $zoneFilter = request()->query('zone_id');
        $zoneId = is_numeric($zoneFilter) ? (int) $zoneFilter : null;

        return response()->json($ai->statusPayload($zoneId));
    }

    public function correctPlate(Request $request, AiParkingOccupancyService $ai, AiCameraRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
            'track_id' => ['required', 'integer', 'min:0'],
            'plate' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $cameraId = strtoupper(trim($validated['camera_id']));
        $known = collect($registry->cameras())->pluck('id')->map(fn ($id) => strtoupper((string) $id));
        if ($known->isNotEmpty() && ! $known->contains($cameraId) && $ai->latestSnapshot($cameraId) === null) {
            return response()->json(['ok' => false, 'message' => 'Unknown camera.'], 422);
        }

        try {
            $identity = $ai->correctPlate(
                $cameraId,
                (int) $validated['track_id'],
                $validated['plate'],
                $request->user()?->id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Plate updated.',
            'data' => $identity,
        ]);
    }
}
