<?php

namespace App\Services;

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
        'overtime' => 'Overtime Parking',
        'unauthorized' => 'Unauthorized Parking',
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
        $plate = PlateLookup::normalize((string) ($event['plate'] ?? ''));
        $zoneId = (string) ($event['zone_id'] ?? '');
        $trackId = $event['track_id'] ?? null;
        $cameraId = (string) ($event['camera_id'] ?? $cameraId);
        // Never trust client area_id alone — resolve from camera registry.
        $areaId = app(AiCameraRegistry::class)->resolveAreaId(
            $cameraId,
            isset($event['area_id']) ? (int) $event['area_id'] : null
        );
        $areaName = ParkingArea::query()->find($areaId)?->area_name;
        $vehicleDetails = $event['vehicle_details'] ?? null;
        $confidence = isset($event['confidence']) ? (float) $event['confidence'] : null;

        $description = $this->buildDescription($event, $cameraId);

        if ($plate === '') {
            return [
                'status' => 'queued_ui_only',
                'reason' => 'no_plate',
                'type' => $type,
                'zone_id' => $zoneId,
                'track_id' => $trackId,
            ];
        }

        $user = PlateLookup::findUser((string) ($event['plate'] ?? $plate));
        $identity = PlateLookup::identity((string) ($event['plate'] ?? $plate));
        if ($vehicleDetails === null) {
            $vehicleDetails = $identity['vehicle_details'];
        }

        // Unauthorized: unknown plate → UI only (no user to cite)
        if ($type === 'unauthorized' && ! $user) {
            return [
                'status' => 'queued_ui_only',
                'reason' => 'unknown_plate',
                'type' => $type,
                'plate' => $plate,
                'zone_id' => $zoneId,
            ];
        }

        // Other violation types without a registered plate → UI only
        if (! $user) {
            return [
                'status' => 'queued_ui_only',
                'reason' => 'plate_not_registered',
                'type' => $type,
                'plate' => $plate,
                'zone_id' => $zoneId,
            ];
        }

        // Debounce at DB level: same user + type within N minutes
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

        $evidencePath = $this->storeEvidenceJpeg($event['evidence_jpeg_base64'] ?? null);

        ViolationLog::query()->create([
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
            'guard_id' => 'AI-'.$cameraId,
            'status' => 'Active',
            'created_at' => now(),
            'camera_id' => $cameraId,
            'area_id' => $areaId,
            'area_name' => $areaName,
            'vehicle_details' => $vehicleDetails,
            'track_id' => is_numeric($trackId) ? (int) $trackId : null,
            'confidence' => $confidence,
        ]);

        $newStrikes = app(ViolationEnforcementService::class)->syncStrikesFromLogs($user);
        $user->refresh();

        $message = "Your vehicle ({$plate}) was auto-cited by AI parking ({$violationType}). Strikes: {$newStrikes}/".User::MAX_STRIKES.'.';

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => null,
            'title' => "AI Violation: {$violationType}",
            'message' => $message,
            'type' => 'Violation',
            'is_read' => false,
            'created_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new VehicleViolationMail(
                plateNumber: $plate,
                violationType: $violationType,
                description: $description,
            ));
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
            'camera_id' => $cameraId,
            'area_id' => $areaId,
        ];
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
        $debounceMinutes = (int) config('services.ai_parking.violation_debounce_minutes', 10);

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

            $cacheKey = 'ai_parking:unauth:'.$plate;
            if (Cache::has($cacheKey)) {
                continue;
            }

            $user = PlateLookup::findUser((string) ($det['plate'] ?? $plate));
            if (! $user) {
                Cache::put($cacheKey, 1, now()->addMinutes(max(1, $debounceMinutes)));
                $extra[] = [
                    'type' => 'unauthorized',
                    'zone_id' => 'unknown',
                    'track_id' => $det['track_id'] ?? null,
                    'plate' => $plate,
                    'confidence' => $det['confidence'] ?? 0.5,
                    'vehicle_details' => $det['vehicle_details'] ?? null,
                ];

                continue;
            }

            $denied = $user->status === User::STATUS_LOCKED
                || ($user->Gate_access ?? '') === User::GATE_ACCESS_DENIED;
            if ($denied) {
                Cache::put($cacheKey, 1, now()->addMinutes(max(1, $debounceMinutes)));
                $extra[] = [
                    'type' => 'unauthorized',
                    'zone_id' => 'lot',
                    'track_id' => $det['track_id'] ?? null,
                    'plate' => $plate,
                    'confidence' => $det['confidence'] ?? 0.5,
                    'vehicle_details' => $det['vehicle_details'] ?? null,
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
        if (isset($event['track_id'])) {
            $parts[] = 'Track #'.$event['track_id'];
        }

        return implode(' ', $parts);
    }
}
