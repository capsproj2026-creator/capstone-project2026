<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Services\AiParkingOccupancyService;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ParkingController extends Controller
{
    public function index(DashboardStatsService $stats, AiParkingOccupancyService $ai): View
    {
        $user = Auth::user();
        $userStats = $stats->userStats($user);
        $roleName = $user->roleName();

        $zoneStats = ParkingArea::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (ParkingArea $area) => $area->isAccessibleByRole($roleName))
            ->map(function (ParkingArea $area) use ($ai) {
                $slots = ParkingSlot::query()->where('area_id', $area->id)->get(['status']);

                return [
                    'area' => $area,
                    'total' => $slots->count(),
                    'available' => $slots->where('status', 'Available')->count(),
                    'occupied' => $slots->where('status', 'Occupied')->count(),
                    'ai_monitored' => $area->id === $ai->monitoredAreaId(),
                ];
            })
            ->values();

        $roleLabel = match ($roleName) {
            'Student' => 'Student',
            'Staff' => 'Faculty / Staff',
            default => $roleName,
        };

        return view('user.parking', array_merge($userStats, [
            'user' => $user,
            'zoneStats' => $zoneStats,
            'roleLabel' => $roleLabel,
            'aiSnapshot' => $ai->latestSnapshot(),
            'statusUrl' => route('user.parking.status'),
        ]));
    }

    public function status(AiParkingOccupancyService $ai): JsonResponse
    {
        $user = Auth::user();
        $roleName = $user->roleName();
        $payload = $ai->statusPayload();

        $payload['zones'] = collect($payload['zones'])
            ->filter(function (array $zone) use ($roleName) {
                $area = ParkingArea::query()->find($zone['id']);

                return $area && $area->isAccessibleByRole($roleName);
            })
            ->values()
            ->all();

        return response()->json($payload);
    }
}
