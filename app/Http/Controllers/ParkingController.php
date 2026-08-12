<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Services\AiParkingOccupancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParkingController extends Controller
{
    public function index(Request $request, AiParkingOccupancyService $ai): View
    {
        $zoneFilter = $request->query('zone_id', 'All');
        $selectedZone = null;

        $slotsQuery = ParkingSlot::query()
            ->with(['area', 'parkedUser'])
            ->orderBy('area_id')
            ->orderBy('slot_number');

        if ($zoneFilter !== 'All') {
            $zoneId = is_numeric($zoneFilter) ? (int) $zoneFilter : 0;

            if ($zoneId > 0 && ($selectedZone = ParkingArea::query()->find($zoneId))) {
                $slotsQuery->where('area_id', $zoneId);
            } else {
                $zoneFilter = 'All';
            }
        }

        $slots = $slotsQuery->get();

        $stats = (object) [
            'total' => $slots->count(),
            'avail' => $slots->where('status', 'Available')->count(),
            'occ' => $slots->where('status', 'Occupied')->count(),
            'res' => $slots->where('status', 'Reserved')->count(),
            'maint' => $slots->where('status', 'Maintenance')->count(),
        ];

        $occupancyRate = $stats->total > 0 ? round(($stats->occ / $stats->total) * 100) : 0;

        $view = str_contains($request->route()->getName(), 'guard.') ? 'guard.parking' : 'admin.parking';
        $statusRoute = str_contains($request->route()->getName(), 'guard.')
            ? 'guard.parking.status'
            : 'admin.parking.status';

        return view($view, [
            'stats' => $stats,
            'occupancyRate' => $occupancyRate,
            'zones' => ParkingArea::query()->orderBy('id')->get(),
            'zoneFilter' => $zoneFilter,
            'selectedZone' => $selectedZone,
            'slots' => $slots,
            'aiSnapshot' => $ai->latestSnapshot(),
            'aiAreaId' => $ai->monitoredAreaId(),
            'statusUrl' => route($statusRoute, ['zone_id' => $zoneFilter === 'All' ? null : $zoneFilter]),
        ]);
    }

    public function zoneAccess(): View
    {
        return view('admin.parking-zone-access', [
            'zones' => ParkingArea::query()->orderBy('id')->get(),
        ]);
    }

    public function updateAreas(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visible' => ['nullable', 'array'],
            'visible.*' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'array'],
            'roles.*.*' => ['in:Student,Staff,Visitor'],
        ]);

        $visible = $validated['visible'] ?? [];
        $roles = $validated['roles'] ?? [];
        $errors = [];

        foreach (ParkingArea::query()->orderBy('id')->get() as $area) {
            if (array_key_exists($area->id, $visible) && empty($roles[$area->id] ?? [])) {
                $errors["zone_{$area->id}"] = "Select at least one role for \"{$area->area_name}\" when it is visible.";
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        foreach (ParkingArea::query()->orderBy('id')->get() as $area) {
            $areaRoles = array_values(array_unique($roles[$area->id] ?? []));

            $area->update([
                'is_visible' => array_key_exists($area->id, $visible),
                'allowed_roles' => $areaRoles,
            ]);
        }

        return back()->with('success', 'Zone access settings saved.');
    }

    public function updateSlotStatus(Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer'],
            'status' => ['required', 'in:Available,Occupied,Reserved,Maintenance'],
        ]);

        $slot = ParkingSlot::query()->findOrFail((int) $validated['slot_id']);
        $status = $validated['status'];

        $payload = ['status' => $status];
        if (in_array($status, ['Available', 'Maintenance', 'Reserved'], true)) {
            $payload['parked_user_id'] = null;
            $payload['parked_visitor_id'] = null;
        }

        $slot->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'slot' => [
                    'id' => $slot->id,
                    'slot_number' => $slot->slot_number,
                    'status' => $slot->status,
                ],
            ]);
        }

        return back()->with('success', "Slot {$slot->slot_number} set to {$status}.");
    }
}
