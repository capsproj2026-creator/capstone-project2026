<?php

namespace App\Support;

use App\Models\GateLog;
use App\Models\ViolationLog;
use App\Services\GateLogService;
use Illuminate\Support\Facades\Cache;

class GateScanPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromLog(GateLog $log, bool $withStats = true): array
    {
        $log->loadMissing(['user.role', 'user.vehicleType', 'visitor.vehicleType']);

        $uid = trim((string) ($log->rfid_uid ?? ''));
        $user = $log->user;
        $visitor = $log->visitor;
        $isVisitor = (bool) $visitor && ! $user;
        $isUnauthorized = ! $user && ! $visitor && strcasecmp((string) ($log->action ?? ''), 'Override') !== 0;

        $name = $isVisitor
            ? $visitor->displayName()
            : ($user?->displayName() ?? 'Unknown Tag');
        $granted = $log->accessGranted();
        $strikes = (int) ($user?->strike_count ?? 0);

        $initials = strtoupper(
            collect(explode(' ', $name))
                ->filter()
                ->map(fn ($w) => mb_substr($w, 0, 1))
                ->take(2)
                ->join('') ?: ($isUnauthorized ? '?' : 'U')
        );

        $isTemporary = (bool) $user?->isTemporaryAccount();
        $isRemedial = (bool) $user?->isRemedialDeclined();

        $action = (string) ($log->action ?? '');
        $statusLabel = $granted
            ? (match (true) {
                strcasecmp($action, 'Override') === 0 => 'Emergency Open',
                strcasecmp($action, 'Exit') === 0 => 'Exit Granted',
                default => 'Entry Granted',
            })
            : ($log->result ?: 'Access Denied');

        if (strcasecmp($action, 'Override') === 0) {
            $name = $user?->displayName() ?? 'Guard override';
            $isUnauthorized = false;
        }

        $plate = $isVisitor ? ($visitor->plate_number ?? null) : ($user?->plate_number ?? null);
        $vehicleType = $isVisitor
            ? ($visitor->vehicleType?->vehicle_name ?? null)
            : ($user?->vehicleType?->vehicle_name ?? null);
        $violationInfo = self::violationInfoForScan(
            $user?->id,
            $plate,
            $strikes
        );

        $payload = [
            'id' => (string) $log->getKey(),
            'log_number' => $log->daily_log_id ?? (string) $log->getKey(),
            'name' => $name,
            'initials' => $initials,
            'profile_picture_url' => $user ? $user->profilePictureUrl() : '',
            'role' => $isUnauthorized
                ? 'Unknown Tag'
                : ($isVisitor ? 'Visitor' : ($user?->gateRoleLabel() ?? 'Unknown')),
            'is_visitor' => $isVisitor,
            'is_temporary' => $isTemporary,
            'is_remedial' => $isRemedial,
            'is_unauthorized' => $isUnauthorized,
            'temporary_expires_at' => $isTemporary ? $user?->temporary_expires_at?->toIso8601String() : null,
            'remedial_expires_at' => $isRemedial ? $user?->remedial_expires_at?->toIso8601String() : null,
            'temporary_message' => $isTemporary
                ? app(\App\Services\TemporaryRfidService::class)->grantReason()
                : ($isRemedial ? \App\Services\RemedialRfidService::GRANT_REASON : null),
            'register_url' => $isTemporary
                ? app(\App\Services\TemporaryRfidService::class)->registrationUrl($user)
                : null,
            'form_documents_url' => $isRemedial ? route('user.registration.form') : null,
            'purpose' => $isVisitor ? ($visitor->purpose ?? null) : null,
            'id_number' => $user?->id_number,
            'plate_number' => $plate,
            'vehicle_type' => $vehicleType,
            'action' => $log->action,
            'result' => $log->result ?? 'Access Granted',
            'granted' => $granted,
            'gate_id' => $log->gate_id,
            'gate_label' => $log->displayGate(),
            'rfid_uid' => $uid === '' ? null : '••••'.substr($uid, -4),
            'rfid_uid_full' => $log->displayRfid(),
            'reason' => $log->displayReason(),
            'status_label' => $isUnauthorized ? 'Unauthorized Access' : $statusLabel,
            'strike_count' => $violationInfo['strike_count'],
            'has_violations' => $violationInfo['has_violations'],
            'violation_count' => $violationInfo['violation_count'],
            'violation_label' => $violationInfo['violation_label'],
            'latest_violation_type' => $violationInfo['latest_violation_type'],
            'latest_violation_at' => $violationInfo['latest_violation_at'],
            'time' => $log->timestamp?->timezone(config('app.timezone'))->format('h:i A'),
            'scanned_at' => $log->timestamp
                ? $log->timestamp->timezone(config('app.timezone'))->toIso8601String()
                : null,
            'timestamp' => $log->timestamp
                ? ph_datetime($log->timestamp, 'M j, Y · g:i:s A')
                : null,
        ];

        if ($withStats) {
            $gateLogs = app(GateLogService::class);
            $payload['today_entries'] = $gateLogs->todayCount('Entry');
            $payload['today_exits'] = $gateLogs->todayCount('Exit');
        }

        return $payload;
    }

    /**
     * Compact feed item for the Live Gate Monitor In/Out Logs panel.
     *
     * @return array{
     *   id: string, name: string, role: string|null, time: string|null, timestamp: string|null,
     *   action: string|null, plate_number: string|null, vehicle_type: string|null,
     *   granted: bool, is_unauthorized: bool, result: string|null, status_label: string|null
     * }
     */
    public static function feedItem(GateLog $log): array
    {
        $full = self::fromLog($log, withStats: false);

        return [
            'id' => $full['id'],
            'name' => $full['name'],
            'role' => $full['role'] ?? null,
            'time' => $full['time'],
            'timestamp' => $full['timestamp'],
            'action' => $full['action'],
            'plate_number' => $full['plate_number'] ?? null,
            'vehicle_type' => $full['vehicle_type'] ?? null,
            'granted' => (bool) $full['granted'],
            'is_unauthorized' => (bool) ($full['is_unauthorized'] ?? false),
            'result' => $full['result'] ?? null,
            'status_label' => $full['status_label'] ?? null,
        ];
    }

    /**
     * Active / historic violations for the person on the gate card.
     *
     * @return array{
     *   strike_count: int,
     *   has_violations: bool,
     *   violation_count: int,
     *   violation_label: string|null,
     *   latest_violation_type: string|null,
     *   latest_violation_at: string|null
     * }
     */
    private static function violationInfoForScan(?int $userId, ?string $plate, int $strikeCount): array
    {
        $plateNorm = \App\Support\PlateLookup::normalize($plate);

        // The Live Gate Monitor recomputes this for every recent log on every
        // poll (and every recent-feed row), so a short cache avoids re-running
        // the same ViolationLog lookup for the same person/plate many times a
        // minute. Violation records change rarely enough that a few seconds of
        // staleness here is unnoticeable.
        $cacheKey = 'gate_scan:violation_info:'.($userId ?: '0').':'.$plateNorm.':'.$strikeCount;

        return Cache::remember($cacheKey, now()->addSeconds(5), fn () => self::computeViolationInfo($userId, $plate, $plateNorm, $strikeCount));
    }

    /**
     * @return array{
     *   strike_count: int,
     *   has_violations: bool,
     *   violation_count: int,
     *   violation_label: string|null,
     *   latest_violation_type: string|null,
     *   latest_violation_at: string|null
     * }
     */
    private static function computeViolationInfo(?int $userId, ?string $plate, string $plateNorm, int $strikeCount): array
    {
        $query = ViolationLog::query()->orderByDesc('created_at');

        if ($userId) {
            $query->where(function ($q) use ($userId, $plate, $plateNorm) {
                $q->where('user_id', $userId);
                if ($plateNorm !== '') {
                    $q->orWhere('plate_number', $plateNorm);
                    if ($plate && strtoupper(trim($plate)) !== $plateNorm) {
                        $q->orWhere('plate_number', strtoupper(trim($plate)));
                    }
                }
            });
        } elseif ($plateNorm !== '') {
            $query->where(function ($q) use ($plate, $plateNorm) {
                $q->where('plate_number', $plateNorm);
                if ($plate && strtoupper(trim($plate)) !== $plateNorm) {
                    $q->orWhere('plate_number', strtoupper(trim($plate)));
                }
            });
        } else {
            return [
                'strike_count' => $strikeCount,
                'has_violations' => $strikeCount > 0,
                'violation_count' => $strikeCount,
                'violation_label' => $strikeCount > 0
                    ? "{$strikeCount} Strike".($strikeCount === 1 ? '' : 's').' on record'
                    : null,
                'latest_violation_type' => null,
                'latest_violation_at' => null,
            ];
        }

        $logs = $query->limit(20)->get(['violation_type', 'status', 'created_at', 'description']);
        $open = $logs->filter(function ($row) {
            $status = strtolower(trim((string) ($row->status ?? '')));

            return $status === '' || ! in_array($status, ['resolved', 'cleared', 'dismissed'], true);
        });

        $violationCount = max($strikeCount, $open->count());
        if ($logs->isNotEmpty() && $strikeCount === 0) {
            $violationCount = max($open->count(), 1);
        }

        $latest = $open->first() ?? $logs->first();
        $latestType = $latest ? trim((string) ($latest->violation_type ?? '')) : '';
        $hasViolations = $strikeCount > 0 || $logs->isNotEmpty();

        $label = null;
        if ($hasViolations) {
            $parts = [];
            if ($strikeCount > 0) {
                $parts[] = "{$strikeCount} Strike".($strikeCount === 1 ? '' : 's');
            } elseif ($open->isNotEmpty()) {
                $parts[] = $open->count().' Open Violation'.($open->count() === 1 ? '' : 's');
            } else {
                $parts[] = 'Violation on record';
            }
            if ($latestType !== '') {
                $parts[] = $latestType;
            }
            $label = implode(' · ', $parts);
        }

        return [
            'strike_count' => $strikeCount,
            'has_violations' => $hasViolations,
            'violation_count' => $violationCount,
            'violation_label' => $label,
            'latest_violation_type' => $latestType !== '' ? $latestType : null,
            'latest_violation_at' => $latest?->created_at
                ? ph_datetime($latest->created_at, 'M j, Y · g:i A')
                : null,
        ];
    }
}
