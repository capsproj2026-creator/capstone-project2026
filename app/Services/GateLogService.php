<?php

namespace App\Services;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\ParkingSlot;
use App\Models\RegisteredPlate;
use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Visitor;
use App\Support\PlateLookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
     * Guard fallback when the RFID reader is down. Uses the same grant rules as a card tap
     * when the vehicle has an RFID on file, and always writes an Access Log.
     *
     * @return array{
     *     log: GateLog,
     *     action: string,
     *     user: User|null,
     *     visitor: Visitor|null,
     *     name: string,
     *     plate: string,
     *     boom_online: bool
     * }
     */
    public function recordByPlate(string $plateNumber, ?string $forcedAction = null): array
    {
        [$user, $visitor, $plate] = $this->resolveRegisteredPlate($plateNumber);

        $action = $forcedAction ?: ($user ? $this->inferNextAction($user) : 'Entry');
        if (! in_array($action, ['Entry', 'Exit'], true)) {
            throw new InvalidArgumentException('Choose Entry or Exit.');
        }

        $gateId = $action === 'Exit' ? 'GATE-OUT-1' : 'GATE-IN-1';
        $uid = $this->rfidUidFor($user, $visitor);
        $hardware = app(GateHardwareService::class);
        $reason = "Manual plate {$action} — RFID unavailable";
        $rfid = app(RfidAccessService::class);

        $result = null;
        if ($user) {
            $result = $rfid->grantResolvedUser($user, $gateId, $action);
        } elseif ($uid !== '') {
            $result = $rfid->process($uid, $gateId, $action);
            if (($result['code'] ?? '') === 'card_not_registered') {
                $result = null;
            }
        }

        if ($result !== null) {
            $logId = $result['log_id'] ?? null;
            if ($logId) {
                $stamp = $result['granted']
                    ? $reason
                    : mb_substr("Manual plate {$action} — ".trim((string) ($result['message'] ?? 'Access denied')), 0, 200);
                GateLog::query()->whereKey($logId)->update(['reason' => $stamp]);
            }

            if (! ($result['granted'] ?? false)) {
                throw new InvalidArgumentException($result['message'] ?? 'Access was denied for this plate.');
            }

            $log = $logId ? GateLog::query()->find($logId) : null;
            if (! $log) {
                throw new InvalidArgumentException('Unable to record this plate on the access log.');
            }

            if (empty($result['open_shared_boom'])) {
                $hardware->queueOpenCommand('GATE-IN-1', $reason, $action === 'Exit' ? 15000 : 10000);
            }
        } else {
            $this->assertManualAccessAllowed($user, $visitor, $action);
            $log = GateLog::query()->create([
                'user_id' => $user?->id,
                'visitor_id' => $visitor?->id,
                'action' => $action,
                'gate_id' => $gateId,
                'rfid_uid' => 'MANUAL-PLATE',
                'result' => RfidAccessService::STATUS_GRANTED,
                'reason' => $reason,
                'timestamp' => now(),
            ]);

            if ($user) {
                $this->syncParkingOccupancy($user, $action);
            } elseif ($visitor) {
                $visitors = app(VisitorService::class);
                if ($action === 'Entry') {
                    $visitors->markInside($visitor);
                } else {
                    $visitors->recordCampusExit($visitor);
                }
            }

            $hardware->queueOpenCommand('GATE-IN-1', $reason, $action === 'Exit' ? 15000 : 10000);
            GateScanProcessed::dispatchFromLog($log);
        }

        $this->forgetTodayCountCache();

        $name = $visitor?->displayName() ?? $user?->displayName() ?? 'Registered vehicle';
        $shownPlate = strtoupper(trim((string) ($visitor?->plate_number ?: $user?->plate_number ?: $plate)));

        return [
            'log' => $log,
            'action' => $action,
            'user' => $user,
            'visitor' => $visitor,
            'name' => $name,
            'plate' => $shownPlate !== '' ? $shownPlate : $plate,
            'boom_online' => $hardware->isOnline('GATE-IN-1'),
        ];
    }

    /**
     * @return array{0: User|null, 1: Visitor|null, 2: string}
     */
    private function resolveRegisteredPlate(string $plateNumber): array
    {
        $normalized = PlateLookup::normalize($plateNumber);
        if (strlen($normalized) < 2) {
            throw new InvalidArgumentException('Please enter a valid plate number.');
        }

        $candidates = PlateLookup::candidates($normalized);
        $user = User::query()
            ->with(['role', 'vehicleType'])
            ->whereIn('plate_number', $candidates)
            ->first();

        if (! $user) {
            $vehicle = UserVehicle::query()
                ->whereIn('plate_number', $candidates)
                ->orderByDesc('is_primary')
                ->first();
            if ($vehicle) {
                $user = User::query()->with(['role', 'vehicleType'])->find($vehicle->user_id);
            }
        }

        $visitor = null;
        if (! $user) {
            $registered = RegisteredPlate::query()
                ->where('plate_number', $normalized)
                ->where('status', RegisteredPlate::STATUS_ACTIVE)
                ->first();

            if ($registered?->owner_type === RegisteredPlate::OWNER_USER) {
                $user = User::query()->with(['role', 'vehicleType'])->find($registered->owner_id);
            } elseif ($registered?->owner_type === RegisteredPlate::OWNER_VISITOR) {
                $visitor = Visitor::query()
                    ->with(['vehicleType', 'rfidCard'])
                    ->find($registered->visitor_id ?: $registered->owner_id);
            }
        }

        if (! $user && ! $visitor) {
            $visitor = Visitor::query()
                ->with(['vehicleType', 'rfidCard'])
                ->whereIn('status', Visitor::ACTIVE_STATUSES)
                ->whereIn('plate_number', $candidates)
                ->orderByDesc('id')
                ->first();
        }

        if (! $user && ! $visitor) {
            throw new InvalidArgumentException('No registered vehicle found for this plate number.');
        }

        $display = strtoupper(trim($plateNumber));

        return [$user, $visitor, $display !== '' ? $display : $normalized];
    }

    private function rfidUidFor(?User $user, ?Visitor $visitor): string
    {
        if ($user) {
            return trim((string) ($user->rfid_uid ?? ''));
        }

        $visitor?->loadMissing('rfidCard');

        return trim((string) ($visitor?->rfidCard?->rfid_uid ?: $visitor?->rfid_uid ?: ''));
    }

    private function assertManualAccessAllowed(?User $user, ?Visitor $visitor, string $action): void
    {
        if ($user) {
            if ($user->isLocked() || ! $user->canAccessPortal()) {
                throw new InvalidArgumentException($user->loginBlockedReason() ?? 'This account cannot access the campus.');
            }

            if ($action === 'Entry' && ($sanctionReason = $user->parkingSanctionReason()) !== null) {
                throw new InvalidArgumentException($sanctionReason);
            }

            if (! $user->hasGateAccess()) {
                throw new InvalidArgumentException('Gate access is not granted for this vehicle.');
            }

            $last = $this->lastGrantedAction('user_id', $user->id);
        } else {
            if (! $visitor || ! $visitor->isActive() || $visitor->isExpiredByTime()) {
                throw new InvalidArgumentException('This visitor pass is not active.');
            }

            $last = $this->lastGrantedAction('visitor_id', $visitor->id);
        }

        if ($action === 'Entry' && $last === 'Entry') {
            throw new InvalidArgumentException('This vehicle is already inside campus.');
        }

        if ($action === 'Exit' && ($last === null || $last === 'Exit')
            && ! (bool) config('services.rfid.allow_exit_without_entry')) {
            throw new InvalidArgumentException('This vehicle is already outside campus.');
        }
    }

    private function lastGrantedAction(string $column, mixed $id): ?string
    {
        $last = GateLog::query()
            ->where($column, $id)
            ->whereIn('action', ['Entry', 'Exit'])
            ->where(function ($query) {
                $query->whereNull('result')
                    ->orWhere('result', '')
                    ->orWhere('result', RfidAccessService::STATUS_GRANTED)
                    ->orWhere('result', 'Granted');
            })
            ->orderByDesc('timestamp')
            ->value('action');

        return $last ? (string) $last : null;
    }

    private function forgetTodayCountCache(): void
    {
        $today = Carbon::today()->toDateString();
        Cache::forget("gate_log:today_count:Entry:{$today}");
        Cache::forget("gate_log:today_count:Exit:{$today}");
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

    /**
     * Today's Entry/Exit count. The Live Gate Monitor polls this endpoint every
     * few seconds from every open guard workstation, so the raw count query is
     * cached for a couple of seconds to avoid hammering MongoDB with identical
     * aggregation queries for data that only changes on an actual gate scan.
     */
    public function todayCount(string $action): int
    {
        $today = Carbon::today()->toDateString();

        return Cache::remember(
            "gate_log:today_count:{$action}:{$today}",
            now()->addSeconds(2),
            function () use ($action) {
                $start = Carbon::today()->startOfDay();
                $end = Carbon::today()->endOfDay();

                return GateLog::query()
                    ->where('action', $action)
                    ->where(function ($query) {
                        $query->whereNull('result')
                            ->orWhere('result', '')
                            ->orWhere('result', RfidAccessService::STATUS_GRANTED)
                            ->orWhere('result', 'Granted')
                            ->orWhere('result', 'Access Granted');
                    })
                    ->where(function ($query) use ($start, $end) {
                        $query->whereBetween('timestamp', [$start, $end])
                            ->orWhere(function ($q) use ($start, $end) {
                                $q->where('log_date', '>=', $start)->where('log_date', '<=', $end);
                            });
                    })
                    ->count();
            }
        );
    }
}
