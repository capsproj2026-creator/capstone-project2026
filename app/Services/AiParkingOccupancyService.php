<?php

namespace App\Services;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Support\Facades\Cache;

class AiParkingOccupancyService
{
    public const CACHE_KEY = 'ai_parking:last';

    /**
     * Apply occupancy from the AI service.
     * Prefer per-slot statuses when provided; otherwise fall back to vehicle_count (first N).
     *
     * @param  list<array{class?: string, confidence?: float, plate?: string, track_id?: int|null}>  $detections
     * @param  list<array{slot_number?: string, occupied?: bool}>|null  $slots
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function applyOccupancy(
        int $areaId,
        int $vehicleCount,
        string $cameraId = 'CAM-AI-1',
        array $detections = [],
        ?array $slots = null,
        array $events = [],
        string $mode = 'count'
    ): array {
        $area = ParkingArea::query()->findOrFail($areaId);

        $usedSlots = is_array($slots) && count($slots) > 0;
        if ($usedSlots) {
            $stats = $this->applySlotStatuses($areaId, $slots);
            $mode = 'slots';
        } else {
            $stats = $this->applyVehicleCountInternal($areaId, $vehicleCount);
            $mode = 'count';
        }

        $violationResults = [];
        if ($events !== []) {
            $violationResults = app(AiParkingViolationService::class)->processEvents($events, $cameraId);
        }

        // Also evaluate unauthorized from plates on detections
        $authEvents = app(AiParkingViolationService::class)->unauthorizedFromDetections($detections, $cameraId);
        if ($authEvents !== []) {
            $violationResults = array_merge(
                $violationResults,
                app(AiParkingViolationService::class)->processEvents($authEvents, $cameraId)
            );
            $events = array_merge($events, $authEvents);
        }

        $snapshot = [
            'camera_id' => $cameraId,
            'area_id' => $areaId,
            'area_name' => $area->area_name,
            'mode' => $mode,
            'vehicle_count' => $usedSlots ? $stats['occupied'] : $stats['occupied_target'],
            'reported_vehicle_count' => $vehicleCount,
            'capacity' => $stats['capacity'],
            'occupied' => $stats['occupied'],
            'available' => $stats['available'],
            'maintenance' => $stats['maintenance'],
            'slots' => $stats['slot_details'],
            'detections' => $detections,
            'events' => array_values(array_slice($events, -20)),
            'violation_results' => $violationResults,
            'updated_at' => now()->toIso8601String(),
            'updated_at_label' => now()->format('h:i:s A'),
        ];

        Cache::put(self::CACHE_KEY, $snapshot, now()->addMinutes(30));

        return $snapshot;
    }

    /**
     * Legacy entry point used by older callers.
     *
     * @param  list<array{class?: string, confidence?: float}>  $detections
     * @return array<string, mixed>
     */
    public function applyVehicleCount(
        int $areaId,
        int $vehicleCount,
        string $cameraId = 'CAM-AI-1',
        array $detections = []
    ): array {
        return $this->applyOccupancy($areaId, $vehicleCount, $cameraId, $detections);
    }

    /**
     * @param  list<array{slot_number?: string, occupied?: bool}>  $slots
     * @return array<string, mixed>
     */
    private function applySlotStatuses(int $areaId, array $slots): array
    {
        $dbSlots = ParkingSlot::query()
            ->where('area_id', $areaId)
            ->orderBy('slot_number')
            ->get();

        $byNumber = [];
        foreach ($slots as $row) {
            if (! is_array($row)) {
                continue;
            }
            $num = strtoupper(trim((string) ($row['slot_number'] ?? '')));
            if ($num === '') {
                continue;
            }
            $byNumber[$num] = (bool) ($row['occupied'] ?? false);
        }

        $occupied = 0;
        $available = 0;
        $maintenance = 0;
        $details = [];

        foreach ($dbSlots as $slot) {
            if (($slot->status ?? '') === 'Maintenance') {
                $maintenance++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Maintenance',
                    'occupied' => false,
                ];

                continue;
            }

            $key = strtoupper((string) $slot->slot_number);
            $isOcc = $byNumber[$key] ?? false;
            if ($isOcc) {
                $slot->update(['status' => 'Occupied']);
                $occupied++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Occupied',
                    'occupied' => true,
                ];
            } else {
                $slot->update([
                    'status' => 'Available',
                    'parked_user_id' => null,
                ]);
                $available++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Available',
                    'occupied' => false,
                ];
            }
        }

        return [
            'capacity' => $occupied + $available,
            'occupied' => $occupied,
            'available' => $available,
            'maintenance' => $maintenance,
            'occupied_target' => $occupied,
            'slot_details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applyVehicleCountInternal(int $areaId, int $vehicleCount): array
    {
        $slots = ParkingSlot::query()
            ->where('area_id', $areaId)
            ->orderBy('slot_number')
            ->get();

        $updatable = $slots->reject(fn (ParkingSlot $slot) => ($slot->status ?? '') === 'Maintenance')->values();
        $capacity = $updatable->count();
        $occupiedTarget = max(0, min($vehicleCount, $capacity));

        $occupied = 0;
        $available = 0;
        $details = [];

        foreach ($updatable as $index => $slot) {
            if ($index < $occupiedTarget) {
                $slot->update(['status' => 'Occupied']);
                $occupied++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Occupied',
                    'occupied' => true,
                ];
            } else {
                $slot->update([
                    'status' => 'Available',
                    'parked_user_id' => null,
                ]);
                $available++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Available',
                    'occupied' => false,
                ];
            }
        }

        $maintenance = $slots->where('status', 'Maintenance')->count();

        return [
            'capacity' => $capacity,
            'occupied' => $occupied,
            'available' => $available,
            'maintenance' => $maintenance,
            'occupied_target' => $occupiedTarget,
            'slot_details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestSnapshot(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    public function monitoredAreaId(): int
    {
        return (int) config('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID);
    }

    /**
     * Build JSON payload for parking status polls.
     *
     * @return array<string, mixed>
     */
    public function statusPayload(?int $zoneFilter = null): array
    {
        $zones = ParkingArea::query()->orderBy('id')->get();
        $aiAreaId = $this->monitoredAreaId();

        $slotsQuery = ParkingSlot::query()
            ->with(['area', 'parkedUser'])
            ->orderBy('area_id')
            ->orderBy('slot_number');

        if ($zoneFilter && $zoneFilter > 0) {
            $slotsQuery->where('area_id', $zoneFilter);
        }

        $slots = $slotsQuery->get();

        $stats = [
            'total' => $slots->count(),
            'avail' => $slots->where('status', 'Available')->count(),
            'occ' => $slots->where('status', 'Occupied')->count(),
            'res' => $slots->where('status', 'Reserved')->count(),
            'maint' => $slots->where('status', 'Maintenance')->count(),
        ];

        $occupancyRate = $stats['total'] > 0 ? (int) round(($stats['occ'] / $stats['total']) * 100) : 0;

        $zoneStats = $zones->map(function (ParkingArea $area) {
            $zoneSlots = ParkingSlot::query()->where('area_id', $area->id)->get(['status']);

            return [
                'id' => $area->id,
                'area_name' => $area->area_name,
                'ai_monitored' => $area->id === $this->monitoredAreaId(),
                'total' => $zoneSlots->count(),
                'available' => $zoneSlots->where('status', 'Available')->count(),
                'occupied' => $zoneSlots->where('status', 'Occupied')->count(),
            ];
        })->values();

        return [
            'stats' => $stats,
            'occupancy_rate' => $occupancyRate,
            'zone_filter' => $zoneFilter ?: 'All',
            'zones' => $zoneStats,
            'slots' => $slots->map(fn (ParkingSlot $slot) => [
                'id' => $slot->id,
                'area_id' => $slot->area_id,
                'area_name' => $slot->area?->area_name,
                'slot_number' => $slot->slot_number,
                'status' => $slot->status ?? 'Available',
                'parked_user' => $slot->parkedUser?->fullname,
                'parked_id_number' => $slot->parkedUser?->id_number,
                'ai_monitored' => (int) $slot->area_id === $aiAreaId,
            ])->values(),
            'ai' => $this->latestSnapshot(),
            'stream_url' => config('services.ai_parking.stream_url'),
            'updated_at' => now()->format('h:i:s A'),
        ];
    }
}
