<?php

namespace App\Support;

use App\Models\GateLog;
use App\Services\GateLogService;

class GateScanPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromLog(GateLog $log, bool $withStats = true): array
    {
        $log->loadMissing(['user.role', 'visitor']);

        $uid = trim((string) ($log->rfid_uid ?? ''));
        $user = $log->user;
        $visitor = $log->visitor;
        $isVisitor = (bool) $visitor && ! $user;

        $name = $isVisitor
            ? $visitor->displayName()
            : ($user?->displayName() ?? 'Unknown card');
        $granted = $log->accessGranted();
        $strikes = (int) ($user?->strike_count ?? 0);

        $initials = strtoupper(
            collect(explode(' ', $name))
                ->filter()
                ->map(fn ($w) => mb_substr($w, 0, 1))
                ->take(2)
                ->join('') ?: 'U'
        );

        $statusLabel = $granted
            ? ($isVisitor
                ? 'Visitor'
                : ($strikes > 0 ? "{$strikes} Strike".($strikes === 1 ? '' : 's') : 'No Violations'))
            : ($log->result ?: 'Access Denied');

        $payload = [
            'id' => (string) $log->getKey(),
            'log_number' => $log->daily_log_id ?? (string) $log->getKey(),
            'name' => $name,
            'initials' => $initials,
            'profile_picture_url' => $user
                ? $user->profilePictureUrl()
                : 'https://ui-avatars.com/api/?name='.urlencode($name).'&background='.($isVisitor ? '0d9488' : '64748b').'&color=fff&size=256',
            'role' => $isVisitor ? 'Visitor' : ($user?->roleName() ?? 'Unknown'),
            'is_visitor' => $isVisitor,
            'purpose' => $isVisitor ? ($visitor->purpose ?? null) : null,
            'id_number' => $user?->id_number,
            'plate_number' => $isVisitor ? $visitor->plate_number : $user?->plate_number,
            'action' => $log->action,
            'result' => $log->result ?? 'Access Granted',
            'granted' => $granted,
            'gate_id' => $log->gate_id,
            'gate_label' => $log->displayGate(),
            'rfid_uid' => $uid === '' ? null : '••••'.substr($uid, -4),
            'rfid_uid_full' => $log->displayRfid(),
            'reason' => $log->displayReason(),
            'status_label' => $statusLabel,
            'strike_count' => $strikes,
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
}
