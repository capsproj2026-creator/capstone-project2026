<?php

namespace App\Services;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class GateHardwareService
{
    public const GATES = [
        'GATE-IN-1' => 'Entry',
        'GATE-OUT-1' => 'Exit',
    ];

    public const ACTION_OVERRIDE = 'Override';

    public const ONLINE_AFTER_SEC = 12;

    public const COMMAND_TTL_SEC = 20;

    public function normalizeGateId(string $gateId): ?string
    {
        $id = strtoupper(trim($gateId));

        return array_key_exists($id, self::GATES) ? $id : null;
    }

    public function directionFor(string $gateId): string
    {
        $id = $this->normalizeGateId($gateId) ?? strtoupper(trim($gateId));

        return self::GATES[$id] ?? 'Entry';
    }

    public function isOnline(string $gateId): bool
    {
        $id = $this->normalizeGateId($gateId);
        if ($id === null) {
            return false;
        }

        $seen = Cache::get($this->seenKey($id));

        return is_numeric($seen) && (now()->timestamp - (int) $seen) <= self::ONLINE_AFTER_SEC;
    }

    /**
     * ESP32 heartbeat: mark online and consume a pending open command (once).
     *
     * @return array{ok: bool, gate_id: string, open: bool, command: string|null, message?: string}
     */
    public function heartbeat(string $gateId): array
    {
        $id = $this->normalizeGateId($gateId);
        if ($id === null) {
            return [
                'ok' => false,
                'gate_id' => strtoupper(trim($gateId)),
                'open' => false,
                'command' => null,
                'message' => 'Unknown gate_id.',
            ];
        }

        Cache::put($this->seenKey($id), now()->timestamp, now()->addSeconds(45));

        $cmd = Cache::pull($this->openKey($id));
        $open = is_array($cmd);

        return [
            'ok' => true,
            'gate_id' => $id,
            'open' => $open,
            'command' => $open ? 'open' : null,
        ];
    }

    /**
     * Queue a one-shot open for the ESP32 and log the override.
     *
     * @return array{gate_id: string, online: bool, queued: bool, log: GateLog}
     */
    public function queueOpen(string $gateId, User $operator, string $reason): array
    {
        $id = $this->normalizeGateId($gateId);
        if ($id === null) {
            throw new \InvalidArgumentException('Unknown gate.');
        }

        $reason = trim($reason);
        Cache::put($this->openKey($id), [
            'reason' => $reason,
            'operator_id' => $operator->id,
            'queued_at' => now()->toIso8601String(),
        ], now()->addSeconds(self::COMMAND_TTL_SEC));

        $log = GateLog::query()->create([
            'user_id' => $operator->id,
            'visitor_id' => null,
            'action' => self::ACTION_OVERRIDE,
            'gate_id' => $id,
            'rfid_uid' => 'MANUAL-OVERRIDE',
            'result' => RfidAccessService::STATUS_GRANTED,
            'reason' => $reason !== '' ? $reason : 'Guard emergency open',
            'timestamp' => now(),
        ]);

        GateScanProcessed::dispatchFromLog($log);

        return [
            'gate_id' => $id,
            'online' => $this->isOnline($id),
            'queued' => true,
            'log' => $log,
        ];
    }

    /**
     * @return list<array{gate_id: string, direction: string, label: string, online: bool, pending_open: bool, last_seen_at: int|null}>
     */
    public function statuses(): array
    {
        $out = [];
        foreach (self::GATES as $id => $direction) {
            $seen = Cache::get($this->seenKey($id));
            $out[] = [
                'gate_id' => $id,
                'direction' => $direction,
                'label' => $direction === 'Entry' ? 'Entry Gate' : 'Exit Gate',
                'online' => $this->isOnline($id),
                'pending_open' => Cache::has($this->openKey($id)),
                'last_seen_at' => is_numeric($seen) ? (int) $seen : null,
            ];
        }

        return $out;
    }

    private function seenKey(string $gateId): string
    {
        return 'gate:hw:'.$gateId.':seen';
    }

    private function openKey(string $gateId): string
    {
        return 'gate:hw:'.$gateId.':open';
    }
}
