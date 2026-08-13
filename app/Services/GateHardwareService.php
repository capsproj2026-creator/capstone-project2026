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
     * Queue a one-shot open command with no Override log (used when Exit RFID should move the shared Entry boom).
     */
    public function queueOpenCommand(string $gateId, string $reason = 'Shared boom open'): bool
    {
        $id = $this->normalizeGateId($gateId);
        if ($id === null) {
            return false;
        }

        Cache::put($this->openKey($id), [
            'reason' => $reason,
            'queued_at' => now()->toIso8601String(),
        ], now()->addSeconds(self::COMMAND_TTL_SEC));

        return true;
    }

    /**
     * After a successful Entry/Exit RFID grant, open the shared physical boom if needed.
     * Servo is wired only to RFID_SHARED_BOOM_GATE_ID (default GATE-IN-1).
     * - Grant on that board: ESP32 opens locally (no queue needed).
     * - Grant on the other board (Exit): queue open so Entry heartbeat moves the servo.
     */
    public function notifySharedBoomAfterGrant(string $fromGateId, string $direction): void
    {
        $shared = $this->normalizeGateId((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1'));
        if ($shared === null) {
            return;
        }

        $from = $this->normalizeGateId($fromGateId) ?? strtoupper(trim($fromGateId));
        if ($from === $shared) {
            return;
        }

        $this->queueOpenCommand($shared, "Shared boom open after {$direction} at {$from}");
    }

    /**
     * Guard emergency open: always drive the shared boom hardware when configured.
     *
     * @return array{gate_id: string, online: bool, queued: bool, log: GateLog, actuator_gate_id: string}
     */
    public function queueOpen(string $gateId, User $operator, string $reason): array
    {
        $requested = $this->normalizeGateId($gateId);
        if ($requested === null) {
            throw new \InvalidArgumentException('Unknown gate.');
        }

        $shared = $this->normalizeGateId((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1')) ?? $requested;
        $actuatorId = $shared;

        $reason = trim($reason);
        Cache::put($this->openKey($actuatorId), [
            'reason' => $reason,
            'operator_id' => $operator->id,
            'queued_at' => now()->toIso8601String(),
            'requested_gate_id' => $requested,
        ], now()->addSeconds(self::COMMAND_TTL_SEC));

        $log = GateLog::query()->create([
            'user_id' => $operator->id,
            'visitor_id' => null,
            'action' => self::ACTION_OVERRIDE,
            'gate_id' => $requested,
            'rfid_uid' => 'MANUAL-OVERRIDE',
            'result' => RfidAccessService::STATUS_GRANTED,
            'reason' => $reason !== '' ? $reason : 'Guard emergency open',
            'timestamp' => now(),
        ]);

        GateScanProcessed::dispatchFromLog($log);

        return [
            'gate_id' => $requested,
            'actuator_gate_id' => $actuatorId,
            'online' => $this->isOnline($actuatorId),
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
