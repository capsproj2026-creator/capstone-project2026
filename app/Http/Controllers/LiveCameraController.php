<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveCameraController extends Controller
{
    public function index(AiParkingOccupancyService $ai, AiParkingHealthService $health): View
    {
        $routeName = request()->route()?->getName() ?? '';
        $isGuard = str_contains($routeName, 'guard.');
        $layout = $isGuard ? 'layouts.guard' : 'layouts.portal';
        $areaId = $ai->monitoredAreaId();
        $area = ParkingArea::query()->find($areaId);
        $areaName = $area?->area_name ?? 'AI Test Lot';
        $aiHealth = $health->status($isGuard);
        $streamUrl = $health->streamBrowserUrl();
        $aiOnline = $aiHealth['connected'] || $aiHealth['stream_reachable'];

        $parkingUrl = $isGuard
            ? route('guard.parking', ['zone_id' => $areaId])
            : route('admin.parking', ['zone_id' => $areaId]);

        // Figma-style campus camera grid; AI MJPEG is wired to the parking lot feed.
        $cameras = [
            [
                'id' => 'cam-main-entry',
                'name' => 'Main Gate - Entry',
                'location' => 'Main Gate',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-main-exit',
                'name' => 'Main Gate - Exit',
                'location' => 'Main Gate',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-side-gate',
                'name' => 'Side Gate',
                'location' => 'Side Gate',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-parking-a',
                'name' => $areaName,
                'location' => 'Parking Lot A',
                'stream_url' => $streamUrl,
                'online' => $aiOnline,
                'ai_monitored' => true,
                'parking_url' => $parkingUrl,
            ],
            [
                'id' => 'cam-parking-b',
                'name' => 'Parking Lot B',
                'location' => 'Parking Lot B',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-building',
                'name' => 'Building Entrance',
                'location' => 'Main Building',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-emergency',
                'name' => 'Emergency Exit',
                'location' => 'Building B',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
            [
                'id' => 'cam-visitor',
                'name' => 'Visitor Parking',
                'location' => 'Visitor Area',
                'stream_url' => null,
                'online' => false,
                'ai_monitored' => false,
                'parking_url' => null,
            ],
        ];

        $total = count($cameras);
        $online = collect($cameras)->where('online', true)->count();
        $offline = $total - $online;

        return view('cameras.live', [
            'layout' => $layout,
            'streamUrl' => $streamUrl,
            'ai' => $ai->latestSnapshot(),
            'aiHealth' => $aiHealth,
            'aiAreaId' => $areaId,
            'aiAreaName' => $areaName,
            'cameras' => $cameras,
            'cameraStats' => [
                'total' => $total,
                'online' => $online,
                'offline' => $offline,
            ],
            'statusUrl' => $isGuard
                ? route('guard.parking.status')
                : route('admin.parking.status'),
            'parkingUrl' => $parkingUrl,
        ]);
    }

    public function aiMonitor(AiParkingOccupancyService $ai, AiParkingHealthService $health): View
    {
        $areaId = $ai->monitoredAreaId();
        $area = ParkingArea::query()->find($areaId);
        $aiHealth = $health->status(true);

        return view('guard.ai-parking-monitor', [
            'streamUrl' => $health->streamBrowserUrl(),
            'ai' => $ai->latestSnapshot(),
            'aiHealth' => $aiHealth,
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'AI Test Lot',
            'statusUrl' => route('guard.parking.status'),
            'parkingUrl' => route('guard.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function stream(AiParkingHealthService $health): StreamedResponse|Response
    {
        $upstream = $health->upstreamStreamUrl();
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
