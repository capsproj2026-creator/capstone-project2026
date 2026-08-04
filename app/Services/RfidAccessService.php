<?php

namespace App\Services;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Support\Str;

class RfidAccessService
{
    public const STATUS_GRANTED = 'Access Granted';

    public const STATUS_DENIED = 'Access Denied';

    public const STATUS_ALREADY_INSIDE = 'Already Inside';

    public const STATUS_ALREADY_OUTSIDE = 'Already Outside';

    public const STATUS_CARD_NOT_REGISTERED = 'Card Not Registered';

    /**
     * Process an RFID tap from an ESP32 gate reader.
     *
     * @return array{
     *     status: string,
     *     code: string,
     *     granted: bool,
     *     action: string|null,
     *     gate_id: string,
     *     message: string,
     *     user: array<string, mixed>|null,
     *     log_id: mixed
     * }
     */
    public function process(string $uid, string $gateId, string $direction): array
    {
        $uid = $this->normalizeUid($uid);
        $gateId = trim($gateId);
        $direction = $this->normalizeDirection($direction);

        $user = User::query()
            ->with(['role', 'vehicleType'])
            ->where('rfid_uid', $uid)
            ->first();

        if (! $user) {
            $log = $this->logDeniedAttempt(null, $uid, $gateId, $direction, self::STATUS_CARD_NOT_REGISTERED, 'RFID card is not registered in the system.');

            return $this->response(self::STATUS_CARD_NOT_REGISTERED, 'card_not_registered', false, $direction, $gateId, 'RFID card is not registered in the system.', null, $log->id);
        }

        if (! $this->isAccountActive($user)) {
            $reason = $user->loginBlockedReason() ?? 'Account is not active.';
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(
                self::STATUS_DENIED,
                'access_denied',
                false,
                $direction,
                $gateId,
                $reason,
                $this->userPayload($user),
                $log->id
            );
        }

        if (! $user->isCampusVehicleOwner()) {
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_DENIED, 'Only vehicle owners may use the RFID gate.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Only vehicle owners may use the RFID gate.', $this->userPayload($user), $log->id);
        }

        if (! $this->hasRegisteredVehicle($user)) {
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_DENIED, 'No registered vehicle found for this account.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'No registered vehicle found for this account.', $this->userPayload($user), $log->id);
        }

        if (! $user->hasGateAccess()) {
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_DENIED, 'Gate / RFID access has not been granted.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Gate / RFID access has not been granted.', $this->userPayload($user), $log->id);
        }

        $lastAction = $this->lastActionFor($user);

        if ($direction === 'Entry' && $lastAction === 'Entry') {
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_ALREADY_INSIDE, 'Vehicle is already inside campus.');

            return $this->response(self::STATUS_ALREADY_INSIDE, 'already_inside', false, $direction, $gateId, 'Vehicle is already inside campus.', $this->userPayload($user), $log->id);
        }

        if ($direction === 'Exit' && ($lastAction === null || $lastAction === 'Exit')) {
            $log = $this->logDeniedAttempt($user, $uid, $gateId, $direction, self::STATUS_ALREADY_OUTSIDE, 'Vehicle is already outside campus.');

            return $this->response(self::STATUS_ALREADY_OUTSIDE, 'already_outside', false, $direction, $gateId, 'Vehicle is already outside campus.', $this->userPayload($user), $log->id);
        }

        $log = GateLog::query()->create([
            'user_id' => $user->id,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'timestamp' => now(),
        ]);

        $this->syncParkingOccupancy($user, $direction);
        GateScanProcessed::dispatchFromLog($log);

        return $this->response(
            self::STATUS_GRANTED,
            'access_granted',
            true,
            $direction,
            $gateId,
            "{$direction} granted for {$user->fullname}.",
            $this->userPayload($user),
            $log->id
        );
    }

    public function normalizeUid(string $uid): string
    {
        $uid = preg_replace('/^\s*UID\s*:\s*/i', '', $uid) ?? $uid;
        $uid = strtoupper(trim($uid));
        $uid = preg_replace('/[^A-F0-9]/', '', $uid) ?? '';

        return $uid;
    }

    private function normalizeDirection(string $direction): string
    {
        $direction = Str::title(strtolower(trim($direction)));

        return in_array($direction, ['Entry', 'Exit'], true) ? $direction : 'Entry';
    }

    private function isAccountActive(User $user): bool
    {
        return $user->canAccessPortal()
            && $user->hasVerifiedEmail()
            && ! $user->isLocked();
    }

    private function hasRegisteredVehicle(User $user): bool
    {
        $plate = trim((string) ($user->plate_number ?? ''));

        return $user->vehicle_id
            && $plate !== ''
            && ! in_array(strtoupper($plate), ['N/A', 'NA', 'NONE'], true);
    }

    private function lastActionFor(User $user): ?string
    {
        $last = GateLog::query()
            ->where('user_id', $user->id)
            ->whereIn('action', ['Entry', 'Exit'])
            ->where(function ($q) {
                $q->whereNull('result')
                    ->orWhere('result', self::STATUS_GRANTED);
            })
            ->orderByDesc('timestamp')
            ->first();

        return $last?->action;
    }

    private function syncParkingOccupancy(User $user, string $action): void
    {
        $slot = ParkingSlot::query()->where('parked_user_id', $user->id)->first();

        if ($action === 'Entry' && $slot) {
            $slot->update(['status' => 'Occupied']);
        }

        if ($action === 'Exit' && $slot) {
            $slot->update(['status' => 'Available', 'parked_user_id' => null]);
        }
    }

    private function logDeniedAttempt(?User $user, string $uid, string $gateId, string $direction, string $result, string $reason = ''): GateLog
    {
        $log = GateLog::query()->create([
            'user_id' => $user?->id,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => $result,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        GateScanProcessed::dispatchFromLog($log);

        return $log;
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return array<string, mixed>
     */
    private function response(
        string $status,
        string $code,
        bool $granted,
        ?string $action,
        string $gateId,
        string $message,
        ?array $user,
        mixed $logId = null
    ): array {
        return [
            'status' => $status,
            'code' => $code,
            'granted' => $granted,
            'action' => $action,
            'gate_id' => $gateId,
            'message' => $message,
            'user' => $user,
            'log_id' => $logId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'id_number' => $user->id_number,
            'plate_number' => $user->plate_number,
            'role' => $user->roleName(),
        ];
    }
}
