<?php

namespace App\Http\Controllers;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Services\AiParkingOccupancyService;
use App\Services\ParkingLayoutService;
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

        $slots = ParkingSlot::sortNaturally($slotsQuery->get());

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

    public function layout(Request $request, ParkingLayoutService $layout): View
    {
        $zones = ParkingArea::query()->orderBy('id')->get();
        $zoneId = is_numeric($request->query('zone_id')) ? (int) $request->query('zone_id') : 0;
        $selectedZone = $zoneId > 0
            ? $zones->first(fn (ParkingArea $zone) => (int) $zone->id === $zoneId)
            : $zones->first();

        $slots = $selectedZone
            ? ParkingSlot::sortNaturally(
                ParkingSlot::query()
                    ->where('area_id', $selectedZone->id)
                    ->get()
            )
            : collect();

        $occupiedByArea = ParkingSlot::query()
            ->where('status', 'Occupied')
            ->get(['area_id'])
            ->groupBy(fn (ParkingSlot $slot) => (int) $slot->area_id)
            ->map(fn ($group) => $group->count());

        return view('admin.parking-layout', [
            'zones' => $zones,
            'selectedZone' => $selectedZone,
            'slots' => $slots,
            'occupiedByArea' => $occupiedByArea,
            'protectedAreaIds' => $layout->protectedAreaIds(),
            'layoutService' => $layout,
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

    public function storeArea(Request $request, ParkingLayoutService $layout): RedirectResponse
    {
        $validated = $request->validate([
            'area_name' => ['required', 'string', 'max:120'],
            'slot_prefix' => ['required', 'string', 'max:8'],
            'slot_count' => ['required', 'integer', 'min:1', 'max:200'],
            'designation_notes' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['nullable', 'boolean'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['in:Student,Staff,Visitor'],
        ]);

        $area = $layout->createArea([
            'area_name' => $validated['area_name'],
            'slot_prefix' => $validated['slot_prefix'],
            'slot_count' => (int) $validated['slot_count'],
            'designation_notes' => $validated['designation_notes'] ?? null,
            'is_visible' => $request->boolean('is_visible', true),
            'allowed_roles' => $validated['allowed_roles'] ?? ['Student', 'Staff'],
        ]);

        return redirect()
            ->route('admin.parking.layout', ['zone_id' => $area->id])
            ->with('success', "Parking area \"{$area->area_name}\" added with {$area->capacity} space(s).");
    }

    public function destroyArea(int $id, ParkingLayoutService $layout): RedirectResponse
    {
        $area = ParkingArea::query()->findOrFail($id);
        $name = $area->area_name;
        $layout->deleteArea($area);

        return redirect()
            ->route('admin.parking.layout')
            ->with('success', "Parking area \"{$name}\" removed.");
    }

    public function storeSlots(Request $request, ParkingLayoutService $layout): RedirectResponse
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer'],
            'slot_count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $area = ParkingArea::query()->findOrFail((int) $validated['area_id']);
        $added = $layout->addSlots($area, (int) $validated['slot_count']);

        return redirect()
            ->route('admin.parking.layout', ['zone_id' => $area->id])
            ->with('success', "Added {$added} parking space(s) to {$area->area_name}.");
    }

    public function destroySlot(int $id, ParkingLayoutService $layout): RedirectResponse
    {
        $slot = ParkingSlot::query()->findOrFail($id);
        $label = $slot->slot_number;
        $areaId = $slot->area_id;
        $layout->deleteSlot($slot);

        return redirect()
            ->route('admin.parking.layout', ['zone_id' => $areaId])
            ->with('success', "Parking space {$label} removed.");
    }
}
