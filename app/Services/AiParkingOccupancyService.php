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

    /** COCO vehicle classes only — no persons or other objects. */
    private const VEHICLE_TYPES = ['car', 'motorcycle', 'bus', 'truck'];

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
        string $mode = 'count',
        bool $detectionsProvided = false
    ): array {
        $area = ParkingArea::query()->findOrFail($areaId);

        $detections = $this->filterVehicleDetections($detections);
        // Only zero the count when the AI explicitly sent detections and none were vehicles.
        // Count-only posts (no detections key) must keep vehicle_count.
        if ($detectionsProvided && $detections === []) {
            $vehicleCount = 0;
        }

        // Windows php artisan serve handles one request at a time — skip heavy Mongo writes when unchanged.
        $cacheKey = $this->cacheKeyForCamera($cameraId);
        $previous = Cache::get($cacheKey);
        $usedSlots = is_array($slots) && count($slots) > 0;
        if (
            is_array($previous)
            && ! $usedSlots
            && $events === []
            && ($previous['reported_vehicle_count'] ?? null) === $vehicleCount
            && ($previous['area_id'] ?? null) === $areaId
        ) {
            $detections = $this->enrichWithOwners($detections);
            $detections = $this->applyPlateCorrections($cameraId, $detections);
            $detections = $this->attachViolationStatus($detections, $previous['events'] ?? []);

            $snapshot = array_merge($previous, [
                'detections' => $detections,
                'updated_at' => now()->toIso8601String(),
                'updated_at_label' => now()->format('h:i:s A'),
            ]);
            $snapshot = array_merge($snapshot, $this->summarizeMotion($detections));

            $ttl = now()->addMinutes(30);
            Cache::put($cacheKey, $snapshot, $ttl);
            $primaryId = app(AiCameraRegistry::class)->primaryCameraId();
            if (strcasecmp($cameraId, $primaryId) === 0) {
                Cache::put(self::CACHE_KEY, $snapshot, $ttl);
            }

            return $snapshot;
        }

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
        $detections = $this->applyPlateCorrections($cameraId, $detections);
        $detections = $this->attachViolationStatus($detections, $events);

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
        $snapshot = array_merge($snapshot, $this->summarizeMotion($detections));

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
                if (($slot->status ?? '') !== 'Available' || $slot->parked_user_id !== null || $slot->parked_visitor_id !== null) {
                    $slot->update([
                        'status' => 'Available',
                        'parked_user_id' => null,
                        'parked_visitor_id' => null,
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
                if (($slot->status ?? '') !== 'Available' || $slot->parked_user_id !== null || $slot->parked_visitor_id !== null) {
                    $slot->update([
                        'status' => 'Available',
                        'parked_user_id' => null,
                        'parked_visitor_id' => null,
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
     * Latest plate reads across all AI cameras (for guard monitors / demos).
     *
     * @return list<array<string, mixed>>
     */
    public function plateScansFromAllCameras(): array
    {
        $scans = [];
        $seen = [];

        foreach ($this->allSnapshots() as $cameraId => $snap) {
            if (! is_array($snap)) {
                continue;
            }
            foreach ($snap['detections'] ?? [] as $det) {
                if (! is_array($det)) {
                    continue;
                }
                $status = strtolower(trim((string) ($det['plate_status'] ?? '')));
                if ($status === 'unreadable') {
                    $key = $cameraId.':unreadable:'.($det['track_id'] ?? '');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $scans[] = array_merge($det, [
                        'camera_id' => $cameraId,
                        'plate' => null,
                        'plate_status' => 'unreadable',
                        'registration_status' => 'Plate Unreadable',
                    ]);

                    continue;
                }

                $plate = trim((string) ($det['plate'] ?? ''));
                if ($plate === '') {
                    continue;
                }

                $key = PlateLookup::normalize($plate).':'.$cameraId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $scans[] = array_merge($det, ['camera_id' => $cameraId]);
            }
        }

        return $scans;
    }

    /**
     * Guard-corrected plate for a tracked vehicle. Survives the next occupancy posts.
     *
     * @return array<string, mixed>
     */
    public function correctPlate(string $cameraId, ?int $trackId, string $plate, ?int $userId = null): array
    {
        $cameraId = strtoupper(trim($cameraId));
        $normalized = PlateLookup::normalize($plate);
        if ($normalized === '' || $trackId === null) {
            throw new \InvalidArgumentException('Camera, track, and plate are required.');
        }

        $key = $this->correctionsKey($cameraId);
        $map = Cache::get($key, []);
        if (! is_array($map)) {
            $map = [];
        }
        $map[(string) $trackId] = [
            'plate' => $normalized,
            'user_id' => $userId,
            'at' => now()->toIso8601String(),
        ];
        Cache::put($key, $map, now()->addHours(2));

        $snap = $this->latestSnapshot($cameraId);
        if (is_array($snap)) {
            $snap['detections'] = $this->applyPlateCorrections($cameraId, $snap['detections'] ?? []);
            $snap['updated_at'] = now()->toIso8601String();
            $snap['updated_at_label'] = now()->format('h:i:s A');
            $ttl = now()->addMinutes(30);
            Cache::put($this->cacheKeyForCamera($cameraId), $snap, $ttl);
            $primaryId = app(AiCameraRegistry::class)->primaryCameraId();
            if (strcasecmp($cameraId, $primaryId) === 0) {
                Cache::put(self::CACHE_KEY, $snap, $ttl);
            }
        }

        $identity = PlateLookup::identity($normalized);
        $identity['track_id'] = $trackId;
        $identity['camera_id'] = $cameraId;
        $identity['plate_corrected'] = true;

        return $identity;
    }

    /**
     * @param  list<array<string, mixed>>  $detections
     * @return list<array<string, mixed>>
     */
    private function applyPlateCorrections(string $cameraId, array $detections): array
    {
        $map = Cache::get($this->correctionsKey($cameraId), []);
        if (! is_array($map) || $map === []) {
            return $detections;
        }

        $changed = false;
        foreach ($detections as $i => $det) {
            if (! is_array($det)) {
                continue;
            }
            $tid = isset($det['track_id']) ? (string) $det['track_id'] : '';
            if ($tid === '' || ! isset($map[$tid]['plate'])) {
                continue;
            }
            $detections[$i]['plate'] = $map[$tid]['plate'];
            $detections[$i]['plate_status'] = 'ok';
            $detections[$i]['plate_corrected'] = true;
            $changed = true;
        }

        return $changed ? $this->enrichWithOwners($detections) : $detections;
    }

    private function correctionsKey(string $cameraId): string
    {
        return 'ai_parking:plate_corrections:'.strtoupper(trim($cameraId));
    }

    /**
     * Attach plate status + registered owner identity to detections/events.
     *
     * Display contract:
     * - plate_status=unreadable → "Plate Unreadable" (no invented plate text)
     * - registered match → owner full name, plate, vehicle details, registration status
     * - readable but unmatched → "Unknown Vehicle" / "Plate Not Registered"
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function enrichWithOwners(array $rows): array
    {
        $hasPlate = false;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = strtolower(trim((string) ($row['plate_status'] ?? '')));
            if ($status === 'unreadable') {
                continue;
            }
            if (trim((string) ($row['plate'] ?? '')) !== '') {
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

            $status = strtolower(trim((string) ($row['plate_status'] ?? '')));
            $plate = trim((string) ($row['plate'] ?? ''));

            if ($status === 'unreadable' || strcasecmp($plate, 'UNREADABLE') === 0) {
                $row['plate'] = null;
                $row['plate_status'] = 'unreadable';
                $row['plate_label'] = 'Plate Unreadable';
                $row['owner_name'] = null;
                $row['owner_label'] = null;
                $row['owner_id_number'] = null;
                $row['owner_role'] = null;
                $row['user_id'] = null;
                $row['registered'] = null;
                $row['vehicle_details'] = null;
                $row['department'] = null;
                $row['registration_status'] = null;

                return $row;
            }

            if ($plate === '') {
                $row['plate_status'] = $status !== '' ? $status : 'pending';
                $row['plate_label'] = null;
                $row['owner_label'] = null;

                return $row;
            }

            $identity = PlateLookup::identity($plate);
            $row['plate'] = $identity['plate'] !== '' ? $identity['plate'] : $plate;
            $row['plate_status'] = 'ok';
            $row['plate_label'] = $row['plate'];
            $row['registered'] = $identity['registered'];
            $row['owner_name'] = $identity['owner_name'];
            $row['owner_label'] = $identity['owner_label'];
            $row['owner_id_number'] = $identity['id_number'];
            $row['owner_role'] = $identity['role'];
            $row['user_id'] = $identity['user_id'];
            $row['vehicle_details'] = $identity['vehicle_details'];
            $row['department'] = $identity['department'];
            $row['registration_status'] = $identity['registration_status'];

            if ($identity['registered']) {
                $row['owner_label'] = $identity['owner_name'];
                $row['registration_status'] = $identity['registration_status'] ?: 'Registered';
            } else {
                $row['owner_label'] = 'Unknown Vehicle';
                $row['registration_status'] = 'Plate Not Registered';
                $row['owner_name'] = null;
            }

            return $row;
        }, $rows);
    }

    /**
     * Count parked vs moving vehicles from AI detections.
     *
     * @param  list<array<string, mixed>>  $detections
     * @return array{parked_count: int, moving_count: int, settling_count: int}
     */
    private function summarizeMotion(array $detections): array
    {
        $parked = 0;
        $moving = 0;
        $settling = 0;

        foreach ($detections as $det) {
            if (! is_array($det)) {
                continue;
            }
            $state = strtolower(trim((string) ($det['motion_state'] ?? '')));
            match ($state) {
                'parked' => $parked++,
                'moving' => $moving++,
                'idle' => $settling++,
                default => null,
            };
        }

        return [
            'parked_count' => $parked,
            'moving_count' => $moving,
            'settling_count' => $settling,
        ];
    }

    /**
     * Keep only COCO vehicle detections (car, motorcycle, bus, truck).
     *
     * @param  list<array<string, mixed>>  $detections
     * @return list<array<string, mixed>>
     */
    private function filterVehicleDetections(array $detections): array
    {
        return array_values(array_filter($detections, function (array $det): bool {
            $class = strtolower(trim((string) ($det['class'] ?? $det['vehicle_type'] ?? '')));

            return in_array($class, self::VEHICLE_TYPES, true);
        }));
    }

    /**
     * Flag detections that match a recent AI violation event (track_id or plate).
     *
     * @param  list<array<string, mixed>>  $detections
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function attachViolationStatus(array $detections, array $events): array
    {
        $byTrack = [];
        $byPlate = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            $type = (string) ($event['type'] ?? '');
            if ($type === '') {
                continue;
            }
            if (isset($event['track_id']) && is_numeric($event['track_id'])) {
                $byTrack[(int) $event['track_id']] = $type;
            }
            $plate = PlateLookup::normalize((string) ($event['plate'] ?? ''));
            if ($plate !== '') {
                $byPlate[$plate] = $type;
            }
        }

        if ($byTrack === [] && $byPlate === []) {
            return $detections;
        }

        return array_map(function ($row) use ($byTrack, $byPlate) {
            if (! is_array($row)) {
                return $row;
            }
            $tid = isset($row['track_id']) && is_numeric($row['track_id']) ? (int) $row['track_id'] : null;
            $plate = PlateLookup::normalize((string) ($row['plate'] ?? ''));
            $type = ($tid !== null ? ($byTrack[$tid] ?? null) : null) ?? ($plate !== '' ? ($byPlate[$plate] ?? null) : null);
            if ($type !== null) {
                $row['violation_status'] = $type;
                $row['violation_flag'] = true;
            }

            return $row;
        }, $detections);
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
            'ai_health' => $health->status($isGuard, null, false),
            'ai_cameras_health' => $health->statusAll($isGuard, false),
            'stream_url' => config('services.ai_parking.stream_url'),
            'cameras' => $registry->cameras(),
            'updated_at' => now()->format('h:i:s A'),
        ];
    }
}
