<?php

namespace App\Services;

use App\Events\AiParkingRealtime;
use App\Mail\VehicleViolationMail;
use App\Models\Notification;
use App\Models\ParkingArea;
use App\Models\User;
use App\Models\ViolationLog;
use App\Support\PlateLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Create parking violations from the YOLO AI service (same shape as guard flow).
 */
class AiParkingViolationService
{
    /** @var array<string, string> */
    public const TYPE_MAP = [
        'no_parking' => 'Wrong Parking',
        'aisle_blocked' => 'Wrong Parking',
        'double_park' => 'Wrong Parking',
        'overtime' => 'Wrong Parking',
        // Unknown / access-denied plates map to Wrong Parking when a registered owner exists.
        'unauthorized' => 'Wrong Parking',
        // Student (etc.) in faculty/staff/reserved lot.
        'wrong_role' => 'Wrong Parking',
        // Motorcycle in car slot / car in motorcycle lot.
        'wrong_vehicle' => 'Wrong Parking',
    ];

    /** Human-readable reasons for AI Violation Events UI. */
    public const REASON_LABELS = [
        'no_parking' => 'Parked in no-parking zone',
        'aisle_blocked' => 'Blocking aisle / drive lane',
        'double_park' => 'Occupying multiple parking slots',
        'overtime' => 'Overtime parking',
        'unauthorized' => 'Unauthorized / restricted vehicle',
        'wrong_role' => 'Wrong parking area for role',
        'wrong_vehicle' => 'Wrong vehicle type for parking area',
    ];

    /** Violation types that are limited to one citation per calendar day per user/vehicle. */
    private const ONCE_PER_DAY_TYPES = [
        'Wrong Parking',
    ];

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    public function processEvents(array $events, string $cameraId = 'CAM-AI-1'): array
    {
        $results = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = (string) ($event['type'] ?? '');
            if ($type === '' || ! isset(self::TYPE_MAP[$type])) {
                continue;
            }

            $results[] = $this->handleOne($event, $cameraId);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function handleOne(array $event, string $cameraId): array
    {
        $type = (string) $event['type'];
        $violationType = self::TYPE_MAP[$type];
        $rawPlate = (string) ($event['plate'] ?? '');
        $plate = PlateLookup::normalize($rawPlate);
        // Prefer null plate + plate_status over inventing a fake "UNKNOWN" plate number.
        $plateStatus = strtolower((string) ($event['plate_status'] ?? ''));
        if ($plate === '' && in_array($plateStatus, ['not_read', 'unreadable'], true)) {
            $plate = '';
        }
        $zoneId = (string) ($event['zone_id'] ?? '');
        $trackId = $event['track_id'] ?? null;
        $cameraId = (string) ($event['camera_id'] ?? $cameraId);
        $vehicleEventId = (string) ($event['vehicle_event_id'] ?? $event['vehicleEventId'] ?? '');
        if ($vehicleEventId === '') {
            $parkingSession = (string) ($event['parking_session_id'] ?? '');
            $type = (string) ($event['type'] ?? '');
            $zoneId = (string) ($event['zone_id'] ?? '');
            if ($parkingSession !== '') {
                $vehicleEventId = $parkingSession.':'.$type.':'.$zoneId;
            } elseif ($trackId !== null) {
                $session = $event['recognition_session_id'] ?? $event['session_id'] ?? 't';
                $vehicleEventId = $cameraId.':session:'.$session.':track:'.$trackId.':'.$type;
            }
        }
        // Never trust client area_id alone — resolve from camera registry.
        $areaId = app(AiCameraRegistry::class)->resolveAreaId(
            $cameraId,
            isset($event['area_id']) ? (int) $event['area_id'] : null
        );
        $area = $areaId > 0 ? ParkingArea::query()->find($areaId) : null;
        $areaName = $area?->area_name;
        $openLot = $area?->isOpenToEveryone() ?? false;
        $vehicleDetails = $event['vehicle_details'] ?? null;
        $confidence = isset($event['confidence']) ? (float) $event['confidence'] : null;
        $detectionSource = strtoupper((string) ($event['detection_source'] ?? $event['plate_source'] ?? 'AI'));
        if (in_array($detectionSource, ['GUARD', 'MANUAL'], true)) {
            $detectionSource = 'MANUAL';
        } elseif (in_array($detectionSource, ['AI_OCR', 'OCR', 'AI', ''], true)) {
            $detectionSource = $detectionSource === '' ? 'AI' : (str_starts_with($detectionSource, 'AI') ? 'AI' : 'OCR');
            if ($detectionSource === 'OCR') {
                $detectionSource = 'AI';
            }
        }

        $description = $this->buildDescription($event, $cameraId);

        if ($vehicleEventId !== '') {
            $existingEarly = ViolationLog::query()
                ->where('vehicle_event_id', $vehicleEventId)
                ->first();
            if ($existingEarly) {
                return [
                    'status' => 'debounced',
                    'reason' => 'vehicle_event_id',
                    'type' => $type,
                    'plate' => $plate !== '' ? $plate : null,
                    'violation_log_id' => (string) $existingEarly->getKey(),
                ];
            }
        }

        if ($plate !== '' && $this->shouldLimitOncePerDay($violationType)) {
            $knownUser = PlateLookup::findUser((string) ($event['plate'] ?? $plate));
            if ($knownUser && $this->alreadyCitedToday((int) $knownUser->id, $plate, $violationType)) {
                return [
                    'status' => 'debounced',
                    'reason' => 'once_per_day',
                    'type' => $type,
                    'plate' => $plate,
                    'user_id' => $knownUser->id,
                ];
            }
        }

        AiParkingRealtime::emit(AiParkingRealtime::EVENT_VIOLATION_DETECTED, [
            'vehicleEventId' => $vehicleEventId !== '' ? $vehicleEventId : null,
            'trackingId' => is_numeric($trackId) ? (int) $trackId : $trackId,
            'plateNumber' => $plate !== '' ? $plate : null,
            'plateStatus' => $plateStatus !== '' ? $plateStatus : ($plate !== '' ? null : 'not_read'),
            'violationType' => $violationType,
            'violationReason' => self::REASON_LABELS[$type] ?? $type,
            'eventType' => $type,
            'cameraId' => $cameraId,
            'parkingArea' => $areaName,
            'detectionSource' => $detectionSource,
        ]);

        // Idempotency: same physical vehicle event must not create multiple DB rows.
        if ($vehicleEventId !== '') {
            $existing = ViolationLog::query()
                ->where('vehicle_event_id', $vehicleEventId)
                ->first();
            if ($existing) {
                return [
                    'status' => 'debounced',
                    'reason' => 'vehicle_event_id',
                    'type' => $type,
                    'plate' => $plate !== '' ? $plate : null,
                    'violation_log_id' => (string) $existing->getKey(),
                ];
            }
        }

        if ($plate === '') {
            return [
                'status' => 'queued_ui_only',
                'reason' => 'no_plate',
                'plate_status' => $plateStatus !== '' ? $plateStatus : 'not_read',
                'type' => $type,
                'zone_id' => $zoneId,
                'track_id' => $trackId,
            ];
        }

        $user = PlateLookup::findUser((string) ($event['plate'] ?? $plate));
        $identity = PlateLookup::identity((string) ($event['plate'] ?? $plate));
        if ($vehicleDetails === null) {
            $vehicleDetails = $identity['vehicle_details'] ?? null;
        }
        $ownerRole = $event['owner_role'] ?? $event['role'] ?? ($identity['role'] ?? null);

        // Unknown plate → notify guards once/day, no user citation (no dummy user created).
        // Open lots (all roles) do not treat unknown plates as Wrong Parking.
        if ($type === 'unauthorized' && ! $user) {
            if ($openLot) {
                return [
                    'status' => 'skipped',
                    'reason' => 'open_lot_unknown_ok',
                    'type' => $type,
                    'plate' => $plate,
                    'zone_id' => $zoneId,
                ];
            }
            $guardNotified = $this->notifyGuardsOncePerDay(
                plate: $plate,
                violationType: 'Wrong Parking',
                title: 'AI Alert: Unregistered vehicle',
                message: "Camera {$cameraId} detected unregistered plate {$plate}"
                    .($areaName ? " at {$areaName}" : '')
                    .'. No registered owner — review on AI Parking Monitor.',
            );

            return [
                'status' => 'queued_ui_only',
                'reason' => 'unknown_plate',
                'type' => $type,
                'plate' => $plate,
                'zone_id' => $zoneId,
                'guard_notified' => $guardNotified,
            ];
        }

        // Other violation types without a registered plate → UI only + guard alert once/day
        if (! $user) {
            if ($openLot && in_array($type, ['unauthorized', 'wrong_role'], true)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'open_lot_unknown_ok',
                    'type' => $type,
                    'plate' => $plate,
                    'zone_id' => $zoneId,
                ];
            }
            $guardNotified = false;
            if (in_array($violationType, self::ONCE_PER_DAY_TYPES, true)) {
                $guardNotified = $this->notifyGuardsOncePerDay(
                    plate: $plate,
                    violationType: $violationType,
                    title: "AI Alert: {$violationType}",
                    message: "Camera {$cameraId} detected {$violationType} for plate {$plate}"
                        .($areaName ? " at {$areaName}" : '')
                        .'. Plate is not registered.',
                );
            }

            return [
                'status' => 'queued_ui_only',
                'reason' => 'plate_not_registered',
                'type' => $type,
                'plate' => $plate,
                'zone_id' => $zoneId,
                'guard_notified' => $guardNotified,
            ];
        }

        // Wrong / Unauthorized: one citation per calendar day per user or plate.
        if ($this->shouldLimitOncePerDay($violationType)) {
            if ($this->alreadyCitedToday($user->id, $plate, $violationType)) {
                return [
                    'status' => 'debounced',
                    'reason' => 'once_per_day',
                    'type' => $type,
                    'plate' => $plate,
                    'user_id' => $user->id,
                ];
            }
        } else {
            // Overtime (and any other types): short minute debounce
            $debounceMinutes = (int) config('services.ai_parking.violation_debounce_minutes', 10);
            $recent = ViolationLog::query()
                ->where('user_id', $user->id)
                ->where('violation_type', $violationType)
                ->where('created_at', '>=', now()->subMinutes(max(1, $debounceMinutes)))
                ->exists();

            if ($recent) {
                return [
                    'status' => 'debounced',
                    'type' => $type,
                    'plate' => $plate,
                    'user_id' => $user->id,
                ];
            }
        }

        // For unauthorized on a known user: only if locked / gate denied
        if ($type === 'unauthorized') {
            $denied = $user->status === User::STATUS_LOCKED
                || ($user->Gate_access ?? '') === User::GATE_ACCESS_DENIED;
            if (! $denied) {
                return [
                    'status' => 'skipped',
                    'reason' => 'user_authorized',
                    'plate' => $plate,
                    'user_id' => $user->id,
                ];
            }
        }

        // wrong_role / wrong_vehicle always cite when plate resolves to a user.

        $evidencePath = $this->storeEvidenceJpeg($event['evidence_jpeg_base64'] ?? null);

        $log = ViolationLog::query()->create([
            'user_id' => $user->id,
            'violator_name' => $user->displayName(),
            'id_number' => $user->id_number,
            'user_type' => in_array((int) $user->user_role_id, [3, 4], true)
                ? ($user->roleName())
                : 'Other',
            'plate_number' => $plate,
            'violation_type' => $violationType,
            'description' => $description,
            'evidence_photo' => $evidencePath,
            'evidence_photos' => $evidencePath ? [$evidencePath] : null,
            'guard_id' => 'AI-'.$cameraId,
            'status' => 'Active',
            'created_at' => now(),
            'camera_id' => $cameraId,
            'area_id' => $areaId,
            'area_name' => $areaName,
            'vehicle_details' => $vehicleDetails,
            'track_id' => is_numeric($trackId) ? (int) $trackId : null,
            'confidence' => $confidence,
            'vehicle_event_id' => $vehicleEventId !== '' ? $vehicleEventId : null,
            'detection_source' => $detectionSource,
            'owner_role' => $ownerRole ?: $user->roleName(),
        ]);

        $newStrikes = app(ViolationEnforcementService::class)->syncStrikesFromLogs($user);
        $user->refresh();

        $message = "Your vehicle ({$plate}) was auto-cited by AI parking ({$violationType}). Strikes: {$newStrikes}/".User::MAX_STRIKES.'.';
        $sanction = \App\Support\ViolationSanctionPresenter::labelForStrike($newStrikes);
        if ($sanction) {
            $message .= ' '.$sanction.'.';
        }

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => null,
            'title' => "AI Violation: {$violationType}",
            'message' => $message,
            'type' => 'Violation',
            'violation_log_id' => (string) $log->getKey(),
            'is_read' => false,
            'created_at' => now(),
        ]);

        $ownerLabel = $user->displayName();
        $this->notifyGuards(
            title: "AI Violation: {$violationType}",
            message: "Camera {$cameraId} cited {$plate} ({$ownerLabel})"
                .($areaName ? " at {$areaName}" : '')
                .". {$violationType}. Strikes: {$newStrikes}/".User::MAX_STRIKES.'.',
            violationLogId: (string) $log->getKey(),
        );

        $realtimePayload = [
            'vehicleEventId' => $vehicleEventId !== '' ? $vehicleEventId : (string) $log->getKey(),
            'trackingId' => is_numeric($trackId) ? (int) $trackId : $trackId,
            'plateNumber' => $plate,
            'userId' => $user->id,
            'userRole' => $ownerRole ?: $user->roleName(),
            'vehicleType' => $vehicleDetails,
            'violationType' => $violationType,
            'violationDescription' => $description,
            'parkingArea' => $areaName,
            'timestamp' => optional($log->created_at)?->toIso8601String(),
            'detectionSource' => $detectionSource,
            'confidence' => $confidence,
            'evidenceReference' => $evidencePath,
            'violationLogId' => (string) $log->getKey(),
        ];
        AiParkingRealtime::emit(AiParkingRealtime::EVENT_VIOLATION_CREATED, $realtimePayload);

        $emailSent = false;
        try {
            Mail::to($user->email)->send(new VehicleViolationMail(
                plateNumber: $plate,
                violationType: $violationType,
                description: $description,
                occurredAt: $log->created_at,
                location: $areaName ?: ($cameraId ?: 'Campus'),
                reportedBy: 'AI Parking Camera ('.$cameraId.')',
                evidencePaths: $evidencePath ? [$evidencePath] : [],
                remarks: $description,
                strikeCount: $newStrikes,
            ));
            $emailSent = true;
            AiParkingRealtime::emit(AiParkingRealtime::EVENT_VIOLATION_NOTIFICATION_SENT, [
                'vehicleEventId' => $realtimePayload['vehicleEventId'],
                'plateNumber' => $plate,
                'violationLogId' => (string) $log->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI parking violation email failed: '.$e->getMessage());
        }

        return [
            'status' => 'created',
            'type' => $type,
            'violation_type' => $violationType,
            'plate' => $plate,
            'user_id' => $user->id,
            'strikes' => $newStrikes,
            'evidence_photo' => $evidencePath,
            'evidence_photos' => $evidencePath ? [$evidencePath] : null,
            'camera_id' => $cameraId,
            'area_id' => $areaId,
            'guard_notified' => true,
            'email_sent' => $emailSent,
            'vehicle_event_id' => $vehicleEventId !== '' ? $vehicleEventId : null,
            'violation_log_id' => (string) $log->getKey(),
        ];
    }

    private function shouldLimitOncePerDay(string $violationType): bool
    {
        if (! filter_var(config('services.ai_parking.violation_once_per_day', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return in_array($violationType, self::ONCE_PER_DAY_TYPES, true);
    }

    private function alreadyCitedToday(int $userId, string $plate, string $violationType): bool
    {
        $start = now()->startOfDay();

        return ViolationLog::query()
            ->where('violation_type', $violationType)
            ->where('created_at', '>=', $start)
            ->where(function ($q) use ($userId, $plate) {
                $q->where('user_id', $userId);
                if ($plate !== '') {
                    $q->orWhere('plate_number', $plate);
                }
            })
            ->exists();
    }

    /**
     * Notify all active guards (type Parking so it appears in guard notification UI).
     */
    private function notifyGuards(string $title, string $message, ?string $violationLogId = null): void
    {
        $guardIds = User::query()
            ->where('user_role_id', NavigationService::ROLE_GUARD)
            ->where('status', User::STATUS_GRANTED)
            ->pluck('id');

        foreach ($guardIds as $guardId) {
            Notification::query()->create([
                'user_id' => (int) $guardId,
                'sender_id' => null,
                'title' => $title,
                'message' => $message,
                'type' => 'Parking',
                'violation_log_id' => $violationLogId,
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Guard alert for plates without a citation (unknown / unregistered), once per day.
     */
    private function notifyGuardsOncePerDay(
        string $plate,
        string $violationType,
        string $title,
        string $message,
    ): bool {
        $dayKey = now()->toDateString();
        $cacheKey = 'ai_parking:guard_alert:'.md5($violationType.'|'.$plate.'|'.$dayKey);
        if (Cache::has($cacheKey)) {
            return false;
        }

        $this->notifyGuards($title, $message);
        Cache::put($cacheKey, 1, now()->endOfDay());

        return true;
    }

    private function storeEvidenceJpeg(mixed $base64): ?string
    {
        if (! is_string($base64) || trim($base64) === '') {
            return null;
        }

        $raw = $base64;
        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 32 || strlen($binary) > 600000) {
            return null;
        }

        // Basic JPEG SOI check
        if (substr($binary, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $path = 'violation-evidence/ai-'.Str::uuid()->toString().'.jpg';

        try {
            Storage::disk('private')->put($path, $binary);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('AI parking evidence store failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Infer unauthorized events from detections that include plates.
     *
     * @param  list<array<string, mixed>>  $detections
     * @return list<array<string, mixed>>
     */
    public function unauthorizedFromDetections(array $detections, string $cameraId): array
    {
        $extra = [];
        $dayKey = now()->toDateString();
        $areaId = app(AiCameraRegistry::class)->resolveAreaId($cameraId, null);
        $area = $areaId > 0 ? ParkingArea::query()->find($areaId) : null;
        $openLot = $area?->isOpenToEveryone() ?? false;

        foreach ($detections as $det) {
            if (! is_array($det)) {
                continue;
            }
            $plate = PlateLookup::normalize((string) ($det['plate'] ?? ''));
            if ($plate === '') {
                continue;
            }
            if (strtolower((string) ($det['plate_status'] ?? '')) === 'unreadable') {
                continue;
            }

            // Emit at most once per plate per calendar day into the event stream.
            $cacheKey = 'ai_parking:unauth_evt:'.$plate.':'.$dayKey;
            if (Cache::has($cacheKey)) {
                continue;
            }

            $user = PlateLookup::findUser((string) ($det['plate'] ?? $plate));
            if (! $user) {
                // Open lots (Student + Staff + Visitor) welcome unknown plates — not Wrong Parking.
                if ($openLot) {
                    continue;
                }
                Cache::put($cacheKey, 1, now()->endOfDay());
                $extra[] = [
                    'type' => 'unauthorized',
                    'zone_id' => 'unknown',
                    'track_id' => $det['track_id'] ?? null,
                    'plate' => $plate,
                    'confidence' => $det['confidence'] ?? 0.5,
                    'vehicle_details' => $det['vehicle_details'] ?? null,
                    'camera_id' => $cameraId,
                    'owner_name' => null,
                    'owner_label' => 'Unknown Vehicle',
                ];

                continue;
            }

            $denied = $user->status === User::STATUS_LOCKED
                || ($user->Gate_access ?? '') === User::GATE_ACCESS_DENIED;
            if ($denied) {
                Cache::put($cacheKey, 1, now()->endOfDay());
                $extra[] = [
                    'type' => 'unauthorized',
                    'zone_id' => 'lot',
                    'track_id' => $det['track_id'] ?? null,
                    'plate' => $plate,
                    'confidence' => $det['confidence'] ?? 0.5,
                    'vehicle_details' => $det['vehicle_details'] ?? null,
                    'camera_id' => $cameraId,
                    'owner_name' => $user->displayName(),
                    'owner_label' => $user->displayName(),
                    'owner_role' => $user->displayRoleLabel(),
                    'role' => $user->displayRoleLabel(),
                ];
            }
        }

        return $extra;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function buildDescription(array $event, string $cameraId): string
    {
        $parts = [
            'Auto-detected by AI parking camera '.$cameraId.'.',
            'Event: '.($event['type'] ?? 'unknown'),
            'Zone: '.($event['zone_id'] ?? 'n/a'),
        ];
        if (! empty($event['label'])) {
            $parts[] = 'Label: '.$event['label'];
        }
        if (! empty($event['dwell_minutes'])) {
            $parts[] = 'Dwell: '.$event['dwell_minutes'].' min';
        }
        if (! empty($event['slots']) && is_array($event['slots'])) {
            $parts[] = 'Slots: '.implode(', ', $event['slots']);
        }
        if (! empty($event['reason'])) {
            $parts[] = 'Reason: '.$event['reason'];
        }
        if (! empty($event['owner_role'])) {
            $parts[] = 'Role: '.$event['owner_role'];
        }
        if (! empty($event['vehicle_class']) || ! empty($event['vehicle_details'])) {
            $parts[] = 'Vehicle: '.($event['vehicle_class'] ?? $event['vehicle_details']);
        }
        if (isset($event['track_id'])) {
            $parts[] = 'Track #'.$event['track_id'];
        }

        return implode(' ', $parts);
    }

    /**
     * Role / vehicle-type wrong-parking from enriched detections vs parking area rules.
     *
     * @param  list<array<string, mixed>>  $detections
     * @return list<array<string, mixed>>
     */
    public function wrongParkingFromDetections(array $detections, string $cameraId, ?int $areaId = null): array
    {
        $extra = [];
        $dayKey = now()->toDateString();
        $resolvedAreaId = app(AiCameraRegistry::class)->resolveAreaId($cameraId, $areaId);
        $area = ParkingArea::query()->find($resolvedAreaId);
        if (! $area) {
            return [];
        }

        foreach ($detections as $det) {
            if (! is_array($det)) {
                continue;
            }

            // Only evaluate wrong-parking for vehicles parked inside a calibrated slot.
            $slotId = trim((string) ($det['slot_id'] ?? ''));
            $motion = strtolower((string) ($det['motion_state'] ?? ''));
            $inZone = $slotId !== '' || ! empty($det['in_calibrated_zone']);
            if (! $inZone) {
                continue;
            }
            if ($motion !== '' && $motion !== 'parked') {
                continue;
            }

            $plate = PlateLookup::normalize((string) ($det['plate'] ?? ''));
            $trackId = $det['track_id'] ?? null;
            $sessionId = $det['recognition_session_id'] ?? $det['parking_session_id'] ?? null;
            $role = (string) ($det['role'] ?? $det['owner_role'] ?? '');
            $vehicleRaw = (string) ($det['vehicle_details'] ?? $det['vehicle_type'] ?? $det['class'] ?? '');
            $vehicleClass = ParkingArea::normalizeVehicleClass($vehicleRaw);

            $stableIdent = $plate !== ''
                ? 'p:'.$plate
                : ($sessionId !== null && $sessionId !== ''
                    ? 's:'.$sessionId
                    : 't:'.($trackId ?? 'x'));
            $parkingSessionKey = (string) ($det['parking_session_id']
                ?? ($det['vehicle_event_id'] ?? ($cameraId.':'.$stableIdent.':'.($slotId !== '' ? $slotId : 'lot'))));

            // Vehicle type vs lot designation (motorcycle ↔ automobile).
            if ($vehicleClass !== null && ! $area->allowsVehicleClass($vehicleClass)) {
                $vehicleKey = $plate !== '' ? 'p:'.$plate : $parkingSessionKey;
                $cacheKey = 'ai_parking:wrong_vehicle:'.md5($vehicleKey.'|wrong_vehicle|'.$dayKey);
                if (! Cache::has($cacheKey)) {
                    Cache::put($cacheKey, 1, now()->endOfDay());
                    $zoneLabel = $slotId !== '' ? $slotId : (string) ($area->slot_prefix ?? 'lot');
                    $extra[] = [
                        'type' => 'wrong_vehicle',
                        'zone_id' => $zoneLabel,
                        'track_id' => $trackId,
                        'recognition_session_id' => is_numeric($sessionId) ? (int) $sessionId : $sessionId,
                        'parking_session_id' => $parkingSessionKey,
                        'vehicle_event_id' => $parkingSessionKey.':wrong_vehicle:'.$zoneLabel,
                        'plate' => $plate !== '' ? $plate : null,
                        'plate_status' => $det['plate_status'] ?? ($plate !== '' ? 'ok' : 'not_read'),
                        'confidence' => $det['confidence'] ?? 0.5,
                        'vehicle_details' => $det['vehicle_details'] ?? $vehicleRaw,
                        'vehicle_type' => $det['vehicle_type'] ?? $vehicleRaw,
                        'vehicle_class' => $vehicleClass,
                        'camera_id' => $cameraId,
                        'area_id' => $resolvedAreaId,
                        'owner_name' => $det['owner_name'] ?? null,
                        'owner_role' => $role !== '' ? $role : null,
                        'owner_id_number' => $det['owner_id_number'] ?? $det['id_number'] ?? null,
                        'registration_status' => $det['registration_status'] ?? null,
                        'registered' => $det['registered'] ?? null,
                        'role' => $role !== '' ? $role : null,
                        'violation_type' => 'Wrong Parking',
                        'violation_status' => 'Wrong Parking',
                        'reason' => sprintf(
                            '%s in %s parking area',
                            ucfirst($vehicleClass),
                            implode('/', $area->getAllowedVehicleClasses() ?? ['restricted'])
                        ),
                    ];
                }
            }

            // Student (etc.) in faculty / staff / reserved area.
            if ($plate === '' || $role === '') {
                continue;
            }
            if ($area->allowsOccupantRole($role)) {
                continue;
            }

            $vehicleKey = $plate !== '' ? 'p:'.$plate : $parkingSessionKey;
            $cacheKey = 'ai_parking:wrong_role:'.md5($vehicleKey.'|wrong_role|'.$dayKey);
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, 1, now()->endOfDay());
            $zoneLabel = $slotId !== '' ? $slotId : (string) ($area->slot_prefix ?? 'lot');
            $extra[] = [
                'type' => 'wrong_role',
                'zone_id' => $zoneLabel,
                'track_id' => $trackId,
                'recognition_session_id' => is_numeric($sessionId) ? (int) $sessionId : $sessionId,
                'parking_session_id' => $parkingSessionKey,
                'vehicle_event_id' => $parkingSessionKey.':wrong_role:'.$zoneLabel,
                'plate' => $plate,
                'plate_status' => $det['plate_status'] ?? 'ok',
                'confidence' => $det['confidence'] ?? 0.5,
                'vehicle_details' => $det['vehicle_details'] ?? null,
                'vehicle_type' => $det['vehicle_type'] ?? $vehicleRaw,
                'camera_id' => $cameraId,
                'area_id' => $resolvedAreaId,
                'owner_name' => $det['owner_name'] ?? null,
                'owner_role' => $role,
                'owner_id_number' => $det['owner_id_number'] ?? $det['id_number'] ?? null,
                'registration_status' => $det['registration_status'] ?? null,
                'role' => $role,
                'violation_type' => 'Wrong Parking',
                'violation_status' => 'Wrong Parking',
                'reason' => sprintf(
                    '%s not allowed in %s (allowed: %s)',
                    $role,
                    $area->area_name,
                    implode(', ', $area->getAllowedRoles())
                ),
            ];
        }

        return $extra;
    }
}
