<?php

namespace App\Services;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\User;

class GateHardwareService
{
    public const GATES = [
        'GATE-IN-1' => 'Entry',
        'GATE-OUT-1' => 'Exit',
    ];

    public const ACTION_OVERRIDE = 'Override';

    /** Keep in sync with bootstrap/gate_hardware_store.php */
    public const ONLINE_AFTER_SEC = 12;

    public const COMMAND_TTL_SEC = 60;

    /** How many heartbeats keep repeating open:true so a missed ESP32 parse still opens the servo. */
    public const OPEN_DELIVERIES = 8;

    public function __construct()
    {
        require_once base_path('bootstrap/gate_hardware_store.php');
    }

    public function normalizeGateId(string $gateId): ?string
    {
        return gate_hw_normalize_id($gateId);
    }

    public function directionFor(string $gateId): string
    {
        $id = $this->normalizeGateId($gateId) ?? strtoupper(trim($gateId));

        return self::GATES[$id] ?? 'Entry';
    }

    public function isOnline(string $gateId): bool
    {
        return gate_hw_is_online($gateId);
    }

    /**
     * ESP32 heartbeat: mark online and deliver a pending open command (retried a few times).
     *
     * @return array{ok: bool, gate_id: string, open: bool, command: string|null, message?: string}
     */
    public function heartbeat(string $gateId): array
    {
        return gate_hw_heartbeat($gateId);
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

        return gate_hw_store_open($id, [
            'reason' => $reason,
            'queued_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * After a successful Entry/Exit RFID grant, open the shared physical boom if needed.
     * Servo is wired only to RFID_SHARED_BOOM_GATE_ID (default GATE-IN-1).
     * - Grant on that board: ESP32 opens locally (no queue needed).
     * @return bool True when an open was queued on the Entry (shared boom) ESP32.
     */
    public function notifySharedBoomAfterGrant(string $fromGateId, string $direction): bool
    {
        $shared = $this->normalizeGateId((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1'));
        if ($shared === null) {
            return false;
        }

        $from = $this->normalizeGateId($fromGateId) ?? strtoupper(trim($fromGateId));
        if ($from === $shared) {
            return false;
        }

        $this->queueOpenCommand($shared, "Shared boom open after {$direction} at {$from}");

        return true;
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
        gate_hw_store_open($actuatorId, [
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
            $out[] = [
                'gate_id' => $id,
                'direction' => $direction,
                'label' => $direction === 'Entry' ? 'Entry Gate' : 'Exit Gate',
                'online' => $this->isOnline($id),
                'pending_open' => gate_hw_has_pending_open($id),
                'last_seen_at' => gate_hw_last_seen_at($id),
            ];
        }

        return $out;
    }
}
