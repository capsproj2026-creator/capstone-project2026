<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LiveCameraController extends Controller
{
    public function index(AiParkingOccupancyService $ai): View
    {
        $routeName = request()->route()?->getName() ?? '';
        $isGuard = str_contains($routeName, 'guard.');
        $layout = $isGuard ? 'layouts.guard' : 'layouts.portal';
        $areaId = $ai->monitoredAreaId();
        $area = ParkingArea::query()->find($areaId);
        $areaName = $area?->area_name ?? 'AI Test Lot';
        $streamUrl = config('services.ai_parking.stream_url');
        $aiOnline = filled($streamUrl);

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

    public function aiMonitor(AiParkingOccupancyService $ai): View
    {
        $areaId = $ai->monitoredAreaId();
        $area = ParkingArea::query()->find($areaId);

        return view('guard.ai-parking-monitor', [
            'streamUrl' => config('services.ai_parking.stream_url'),
            'ai' => $ai->latestSnapshot(),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'AI Test Lot',
            'statusUrl' => route('guard.parking.status'),
            'parkingUrl' => route('guard.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function status(AiParkingOccupancyService $ai): JsonResponse
    {
        $zoneFilter = request()->query('zone_id');
        $zoneId = is_numeric($zoneFilter) ? (int) $zoneFilter : null;

        return response()->json($ai->statusPayload($zoneId));
    }
}
