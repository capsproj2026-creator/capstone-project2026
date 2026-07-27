<?php

namespace App\Services;

use App\Models\GateLog;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class GateLogService
{
    public function inferNextAction(User $user): string
    {
        $last = GateLog::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('result')
                    ->orWhere('result', RfidAccessService::STATUS_GRANTED);
            })
            ->orderByDesc('timestamp')
            ->first();

        if (! $last || $last->action === 'Exit') {
            return 'Entry';
        }

        return 'Exit';
    }

    /**
     * @return array{log: GateLog, action: string}
     */
    public function recordByPlate(string $plateNumber, ?string $forcedAction = null): array
    {
        $plate = strtoupper(trim($plateNumber));
        $user = User::query()
            ->with('role')
            ->where(function ($q) use ($plate, $plateNumber) {
                $q->where('plate_number', $plate)
                    ->orWhere('plate_number', trim($plateNumber));
            })
            ->first();

        if (! $user) {
            throw new InvalidArgumentException('No registered vehicle found for this plate number.');
        }

        if (! $user->canAccessPortal()) {
            throw new InvalidArgumentException($user->loginBlockedReason() ?? 'This account cannot access the campus.');
        }

        if (! $user->hasGateAccess()) {
            throw new InvalidArgumentException('RFID / gate access is not granted for this user.');
        }

        $action = $forcedAction ?: $this->inferNextAction($user);

        if (! in_array($action, ['Entry', 'Exit'], true)) {
            throw new InvalidArgumentException('Invalid gate action.');
        }

        $log = GateLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'result' => RfidAccessService::STATUS_GRANTED,
            'timestamp' => now(),
        ]);

        $this->syncParkingOccupancy($user, $action);

        return ['log' => $log, 'action' => $action, 'user' => $user];
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

    public function vehiclesCurrentlyInside(): int
    {
        return ParkingSlot::query()->where('status', 'Occupied')->count();
    }

    public function todayCount(string $action): int
    {
        $today = Carbon::today();

        return GateLog::query()
            ->where('action', $action)
            ->where(function ($query) {
                $query->whereNull('result')
                    ->orWhere('result', RfidAccessService::STATUS_GRANTED);
            })
            ->where('log_date', '>=', $today)
            ->where('log_date', '<', $today->copy()->addDay())
            ->count();
    }
}
