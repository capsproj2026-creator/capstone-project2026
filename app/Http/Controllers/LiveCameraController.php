<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\JsonResponse;
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
        foreach ($registry->cameras() as $cam) {
            $camHealth = $health->status($isGuard, $cam['id']);
            $snap = $ai->latestSnapshot($cam['id']);
            $area = ParkingArea::query()->find($cam['area_id']);
            $parkingUrl = $isGuard
                ? route('guard.parking', ['zone_id' => $cam['area_id']])
                : route('admin.parking', ['zone_id' => $cam['area_id']]);

            $streamUrl = $camHealth['stream_browser_url'] ?? $cam['stream_url'] ?? null;
            $hasStream = filled($streamUrl);

            $cameras[] = [
                'id' => strtolower(str_replace('_', '-', $cam['id'])),
                'camera_id' => $cam['id'],
                'name' => $area?->area_name ?? $cam['name'],
                'location' => $cam['location'],
                'stream_url' => $streamUrl,
                // Show the <img> whenever a stream URL is configured so the browser
                // can connect as soon as the Python MJPEG service comes up.
                'online' => $hasStream,
                'ai_monitored' => true,
                'parking_url' => $parkingUrl,
                'vehicle_count' => $snap['vehicle_count'] ?? null,
                'occupied' => $snap['occupied'] ?? null,
                'available' => $snap['available'] ?? null,
                'updated_at_label' => $snap['updated_at_label'] ?? null,
            ];
        }

        // Keep a few offline campus placeholders so the grid still feels like a VMS.
        foreach ([
            ['id' => 'cam-main-entry', 'name' => 'Main Gate - Entry', 'location' => 'Main Gate'],
            ['id' => 'cam-main-exit', 'name' => 'Main Gate - Exit', 'location' => 'Main Gate'],
            ['id' => 'cam-side-gate', 'name' => 'Side Gate', 'location' => 'Side Gate'],
        ] as $placeholder) {
            $cameras[] = [
                'id' => $placeholder['id'],
                'camera_id' => null,
                'name' => $placeholder['name'],
                'location' => $placeholder['location'],
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
                'vehicle_count' => null,
                'occupied' => null,
                'available' => null,
                'updated_at_label' => null,
            ];
        }

        $primary = $registry->primaryCameraId();
        $areaId = $registry->resolveAreaId($primary);
        $area = ParkingArea::query()->find($areaId);
        $streamUrl = $health->streamBrowserUrl($primary);
        $aiHealth = $health->status($isGuard, $primary);

        $total = count($cameras);
        $online = collect($cameras)->where('online', true)->count();

        return view('cameras.live', [
            'layout' => $layout,
            'streamUrl' => $streamUrl,
            'ai' => $ai->latestSnapshot($primary),
            'aiHealth' => $aiHealth,
            'aiCamerasHealth' => $health->statusAll($isGuard),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'AI Test Lot',
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
        $aiHealth = $health->status(true, $primary);

        return view('guard.ai-parking-monitor', [
            'streamUrl' => $health->streamBrowserUrl($primary),
            'ai' => $ai->latestSnapshot($primary),
            'aiHealth' => $aiHealth,
            'aiCameras' => $ai->allSnapshots(),
            'aiCamerasHealth' => $health->statusAll(true),
            'registryCameras' => $registry->cameras(),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'AI Test Lot',
            'statusUrl' => route('guard.parking.status'),
            'parkingUrl' => route('guard.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function stream(AiParkingHealthService $health, ?string $camera = null): StreamedResponse|Response
    {
        $upstream = $health->upstreamStreamUrl($camera);
        if ($upstream === null) {
            abort(503, 'AI parking stream is not configured.');
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
}
