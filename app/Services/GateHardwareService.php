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

    public const COMMAND_TTL_SEC = 60;

    /** How many heartbeats keep repeating open:true so a missed ESP32 parse still opens the servo. */
    public const OPEN_DELIVERIES = 5;

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
     * ESP32 heartbeat: mark online and deliver a pending open command (retried a few times).
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

        $open = $this->consumeOpenDelivery($id);

        return [
            'ok' => true,
            'gate_id' => $id,
            'open' => $open,
            'command' => $open ? 'open' : null,
        ];
    }

    /**
     * Queue a boom-open command with no Override log (Exit RFID → Entry servo).
     */
    public function queueOpenCommand(string $gateId, string $reason = 'Shared boom open'): bool
    {
        $id = $this->normalizeGateId($gateId);
        if ($id === null) {
            return false;
        }

        $this->storeOpenCommand($id, [
            'reason' => $reason,
            'queued_at' => now()->toIso8601String(),
        ]);

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
     * Guard emergency open: Entry boom servo only (GATE-IN-1 / shared boom ESP32).
     *
     * @return array{gate_id: string, online: bool, queued: bool, log: GateLog, actuator_gate_id: string}
     */
    public function queueOpen(string $gateId, User $operator, string $reason): array
    {
        $requested = $this->normalizeGateId($gateId);
        $shared = $this->normalizeGateId((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1')) ?? 'GATE-IN-1';

        if ($requested === null || $requested !== $shared) {
            throw new \InvalidArgumentException('Emergency open is only available for the Entry boom servo.');
        }

        $actuatorId = $shared;
        $reason = trim($reason);
        $this->storeOpenCommand($actuatorId, [
            'reason' => $reason,
            'operator_id' => $operator->id,
            'queued_at' => now()->toIso8601String(),
            'requested_gate_id' => $actuatorId,
        ]);

        $log = GateLog::query()->create([
            'user_id' => $operator->id,
            'visitor_id' => null,
            'action' => self::ACTION_OVERRIDE,
            'gate_id' => $actuatorId,
            'rfid_uid' => 'MANUAL-OVERRIDE',
            'result' => RfidAccessService::STATUS_GRANTED,
            'reason' => $reason !== '' ? $reason : 'Guard emergency open',
            'timestamp' => now(),
        ]);

        GateScanProcessed::dispatchFromLog($log);

        return [
            'gate_id' => $actuatorId,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeOpenCommand(string $gateId, array $payload): void
    {
        Cache::put($this->openKey($gateId), array_merge($payload, [
            'remain' => self::OPEN_DELIVERIES,
        ]), now()->addSeconds(self::COMMAND_TTL_SEC));
    }

    private function consumeOpenDelivery(string $gateId): bool
    {
        $key = $this->openKey($gateId);
        $cmd = Cache::get($key);
        if (! is_array($cmd)) {
            return false;
        }

        $remain = (int) ($cmd['remain'] ?? 1);
        $remain--;

        if ($remain <= 0) {
            Cache::forget($key);
        } else {
            $cmd['remain'] = $remain;
            Cache::put($key, $cmd, now()->addSeconds(self::COMMAND_TTL_SEC));
        }

        return true;
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
