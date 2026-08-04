<?php

namespace App\Services;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Support\PlateLookup;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Support\Facades\Cache;

class AiParkingOccupancyService
{
    /** @deprecated Use cacheKeyForCamera() — kept for primary-camera backward compatibility */
    public const CACHE_KEY = 'ai_parking:last';

    public function cacheKeyForCamera(string $cameraId): string
    {
        return 'ai_parking:last:'.strtoupper(trim($cameraId));
    }

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

        $authEvents = app(AiParkingViolationService::class)->unauthorizedFromDetections($detections, $cameraId);
        if ($authEvents !== []) {
            $violationResults = array_merge(
                $violationResults,
                app(AiParkingViolationService::class)->processEvents($authEvents, $cameraId)
            );
            $events = array_merge($events, $authEvents);
        }

        $detections = $this->enrichWithOwners($detections);
        $events = $this->enrichWithOwners($events);

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

        $ttl = now()->addMinutes(30);
        Cache::put($this->cacheKeyForCamera($cameraId), $snapshot, $ttl);

        // Keep legacy key in sync for the primary camera so older UIs keep working.
        $primaryId = app(AiCameraRegistry::class)->primaryCameraId();
        if (strcasecmp($cameraId, $primaryId) === 0) {
            Cache::put(self::CACHE_KEY, $snapshot, $ttl);
        }

        return $snapshot;
    }

    /**
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
            if (in_array($slot->status ?? '', ['Maintenance', 'Reserved'], true)) {
                if (($slot->status ?? '') === 'Maintenance') {
                    $maintenance++;
                }
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => $slot->status,
                    'occupied' => false,
                ];

                continue;
            }

            $key = strtoupper((string) $slot->slot_number);
            $isOcc = $byNumber[$key] ?? false;
            if ($isOcc) {
                if (($slot->status ?? '') !== 'Occupied') {
                    $slot->update(['status' => 'Occupied']);
                }
                $occupied++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Occupied',
                    'occupied' => true,
                ];
            } else {
                if (($slot->status ?? '') !== 'Available' || $slot->parked_user_id !== null) {
                    $slot->update([
                        'status' => 'Available',
                        'parked_user_id' => null,
                    ]);
                }
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

        $updatable = $slots->reject(
            fn (ParkingSlot $slot) => in_array($slot->status ?? '', ['Maintenance', 'Reserved'], true)
        )->values();
        $capacity = $updatable->count();
        $occupiedTarget = max(0, min($vehicleCount, $capacity));

        $occupied = 0;
        $available = 0;
        $details = [];

        foreach ($updatable as $index => $slot) {
            if ($index < $occupiedTarget) {
                if (($slot->status ?? '') !== 'Occupied') {
                    $slot->update(['status' => 'Occupied']);
                }
                $occupied++;
                $details[] = [
                    'slot_number' => $slot->slot_number,
                    'status' => 'Occupied',
                    'occupied' => true,
                ];
            } else {
                if (($slot->status ?? '') !== 'Available' || $slot->parked_user_id !== null) {
                    $slot->update([
                        'status' => 'Available',
                        'parked_user_id' => null,
                    ]);
                }
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
    public function latestSnapshot(?string $cameraId = null): ?array
    {
        if ($cameraId) {
            $cached = Cache::get($this->cacheKeyForCamera($cameraId));
            if (is_array($cached)) {
                return $cached;
            }
        }

        $legacy = Cache::get(self::CACHE_KEY);
        if (is_array($legacy)) {
            return $legacy;
        }

        $primary = app(AiCameraRegistry::class)->primaryCameraId();
        $cached = Cache::get($this->cacheKeyForCamera($primary));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allSnapshots(): array
    {
        $out = [];
        foreach (app(AiCameraRegistry::class)->cameras() as $camera) {
            $snap = $this->latestSnapshot($camera['id']);
            if (is_array($snap)) {
                $out[$camera['id']] = $snap;
            }
        }

        return $out;
    }

    /**
     * Attach registered owner identity to detections/events that include a plate.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function enrichWithOwners(array $rows): array
    {
        $hasPlate = false;
        foreach ($rows as $row) {
            if (is_array($row) && trim((string) ($row['plate'] ?? '')) !== '') {
                $hasPlate = true;
                break;
            }
        }

        if ($hasPlate) {
            PlateLookup::warmIndex();
        }

        return array_map(function ($row) {
            if (! is_array($row)) {
                return $row;
            }

            $plate = (string) ($row['plate'] ?? '');
            if (trim($plate) === '') {
                return $row;
            }

            $identity = PlateLookup::identity($plate);
            $row['plate'] = $identity['plate'] !== '' ? $identity['plate'] : $plate;
            $row['registered'] = $identity['registered'];
            $row['owner_name'] = $identity['owner_name'];
            $row['owner_id_number'] = $identity['id_number'];
            $row['owner_role'] = $identity['role'];
            $row['user_id'] = $identity['user_id'];

            return $row;
        }, $rows);
    }

    public function monitoredAreaId(): int
    {
        return (int) config('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(?int $zoneFilter = null): array
    {
        $registry = app(AiCameraRegistry::class);
        $zones = ParkingArea::query()->orderBy('id')->get();
        $monitoredIds = $registry->monitoredAreaIds();
        $aiAreaId = $this->monitoredAreaId();

        $slotsQuery = ParkingSlot::query()
            ->with(['area', 'parkedUser'])
            ->orderBy('area_id')
            ->orderBy('slot_number');

        $allSlots = $slotsQuery->get();
        $slots = ($zoneFilter && $zoneFilter > 0)
            ? $allSlots->where('area_id', $zoneFilter)->values()
            : $allSlots;

        $stats = [
            'total' => $slots->count(),
            'avail' => $slots->where('status', 'Available')->count(),
            'occ' => $slots->where('status', 'Occupied')->count(),
            'res' => $slots->where('status', 'Reserved')->count(),
            'maint' => $slots->where('status', 'Maintenance')->count(),
        ];

        $occupancyRate = $stats['total'] > 0 ? (int) round(($stats['occ'] / $stats['total']) * 100) : 0;

        $slotsByArea = $allSlots->groupBy(fn (ParkingSlot $slot) => (int) $slot->area_id);

        $zoneStats = $zones->map(function (ParkingArea $area) use ($monitoredIds, $slotsByArea) {
            $zoneSlots = $slotsByArea->get((int) $area->id, collect());

            return [
                'id' => $area->id,
                'area_name' => $area->area_name,
                'designation_notes' => $area->designation_notes,
                'ai_monitored' => in_array((int) $area->id, $monitoredIds, true),
                'total' => $zoneSlots->count(),
                'available' => $zoneSlots->where('status', 'Available')->count(),
                'occupied' => $zoneSlots->where('status', 'Occupied')->count(),
                'reserved' => $zoneSlots->where('status', 'Reserved')->count(),
                'maintenance' => $zoneSlots->where('status', 'Maintenance')->count(),
                'slots' => $zoneSlots->map(fn (ParkingSlot $slot) => [
                    'id' => $slot->id,
                    'slot_number' => $slot->slot_number,
                    'status' => $slot->status ?? 'Available',
                ])->values()->all(),
            ];
        })->values();

        $isGuard = str_contains((string) request()->route()?->getName(), 'guard.');
        $health = app(AiParkingHealthService::class);

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
                'ai_monitored' => in_array((int) $slot->area_id, $monitoredIds, true),
            ])->values(),
            'ai' => $this->latestSnapshot(),
            'ai_cameras' => $this->allSnapshots(),
            'ai_health' => $health->status($isGuard),
            'ai_cameras_health' => $health->statusAll($isGuard),
            'stream_url' => config('services.ai_parking.stream_url'),
            'cameras' => $registry->cameras(),
            'updated_at' => now()->format('h:i:s A'),
        ];
    }
}
