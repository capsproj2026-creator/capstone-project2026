<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Services\AiCameraRegistry;
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
            ->filter(fn (ParkingArea $area) => in_array($roleName, $area->getAllowedRoles(), true))
            ->map(function (ParkingArea $area) use ($ai) {
                $hidden = ! $area->isVisibleToUsers();
                $slots = ParkingSlot::query()
                    ->where('area_id', $area->id)
                    ->orderBy('slot_number')
                    ->get(['id', 'slot_number', 'status'])
                    ->map(function (ParkingSlot $slot) use ($hidden) {
                        if ($hidden) {
                            $slot->status = 'Maintenance';
                        }

                        return $slot;
                    });

                return [
                    'area' => $area,
                    'hidden' => $hidden,
                    'total' => $slots->count(),
                    'available' => $hidden ? 0 : $slots->where('status', 'Available')->count(),
                    'occupied' => $hidden ? 0 : $slots->where('status', 'Occupied')->count(),
                    'reserved' => $hidden ? 0 : $slots->where('status', 'Reserved')->count(),
                    'maintenance' => $hidden ? $slots->count() : $slots->where('status', 'Maintenance')->count(),
                    'ai_monitored' => in_array((int) $area->id, app(AiCameraRegistry::class)->monitoredAreaIds(), true)
                        || $area->id === $ai->monitoredAreaId(),
                    'slots' => $slots,
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
            ->map(function (array $zone) use ($roleName) {
                $area = ParkingArea::query()->find($zone['id']);
                if (! $area || ! in_array($roleName, $area->getAllowedRoles(), true)) {
                    return null;
                }

                if (! $area->isVisibleToUsers()) {
                    $slots = collect($zone['slots'] ?? [])->map(function (array $slot) {
                        $slot['status'] = 'Maintenance';

                        return $slot;
                    })->values()->all();

                    return array_merge($zone, [
                        'hidden' => true,
                        'available' => 0,
                        'occupied' => 0,
                        'reserved' => 0,
                        'maintenance' => count($slots),
                        'slots' => $slots,
                    ]);
                }

                $zone['hidden'] = false;

                return $zone;
            })
            ->filter()
            ->values()
            ->all();

        return response()->json($payload);
    }
}
