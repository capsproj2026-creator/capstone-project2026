<?php

namespace App\Services;

use App\Models\GateLog;
use App\Models\OvernightParkingRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Overnight parking (10:00 p.m. – 5:00 a.m.) is prohibited unless the GSU approves an employee's request.
 */
class OvernightParkingService
{
    public const STARTS_AT_HOUR = 22;

    public const ENDS_AT_HOUR = 5;

    /** Gate history scanned when deciding who is still inside campus. */
    private const LOOKBACK_DAYS = 3;

    /**
     * Y-m-d of the night in progress (or tonight, during the day). Night D runs D 10:00 p.m. → D+1 5:00 a.m.
     */
    public function currentNight(?CarbonInterface $now = null): string
    {
        $now ??= ph_now();

        return $now->hour < self::ENDS_AT_HOUR
            ? $now->copy()->subDay()->toDateString()
            : $now->toDateString();
    }

    public function isOvernightWindow(?CarbonInterface $now = null): bool
    {
        $now ??= ph_now();

        return $now->hour >= self::STARTS_AT_HOUR || $now->hour < self::ENDS_AT_HOUR;
    }

    public function hasApprovalFor(User $user, string $night): bool
    {
        return $this->approvedFor($night)->has((int) $user->id);
    }

    /**
     * @return Collection<int, OvernightParkingRequest> keyed by user id
     */
    public function approvedFor(string $night): Collection
    {
        return OvernightParkingRequest::query()
            ->where('status', OvernightParkingRequest::STATUS_APPROVED)
            ->where('first_night', '<=', $night)
            ->where('last_night', '>=', $night)
            ->get()
            ->keyBy(fn (OvernightParkingRequest $r) => (int) $r->user_id);
    }

    /**
     * Registered vehicles whose latest granted gate event is an Entry.
     *
     * @return Collection<int, array{user: User, entered_at: CarbonInterface|null, gate: string}>
     */
    public function vehiclesInsideCampus(): Collection
    {
        $latestByUser = GateLog::query()
            ->whereNotNull('user_id')
            ->whereIn('action', ['Entry', 'Exit'])
            ->where('timestamp', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->where(function ($query) {
                $query->whereNull('result')
                    ->orWhere('result', '')
                    ->orWhere('result', RfidAccessService::STATUS_GRANTED)
                    ->orWhere('result', 'Granted');
            })
            ->orderByDesc('timestamp')
            ->get(['user_id', 'action', 'timestamp', 'gate_id'])
            ->unique(fn (GateLog $log) => (int) $log->user_id)
            ->filter(fn (GateLog $log) => $log->action === 'Entry');

        if ($latestByUser->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', $latestByUser->pluck('user_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy(fn (User $u) => (int) $u->id);

        return $latestByUser
            ->map(fn (GateLog $log) => [
                'user' => $users->get((int) $log->user_id),
                'entered_at' => $log->timestamp,
                'gate' => $log->displayGate(),
            ])
            ->filter(fn (array $row) => $row['user'] !== null)
            ->values();
    }

    /**
     * Vehicles still inside campus for the current night, split by whether the GSU approved them.
     *
     * @return array{night: string, in_window: bool, approved: Collection, unapproved: Collection}
     */
    public function overnightCheck(): array
    {
        $night = $this->currentNight();
        $approved = $this->approvedFor($night);

        $rows = $this->vehiclesInsideCampus()->map(function (array $row) use ($approved) {
            $row['request'] = $approved->get((int) $row['user']->id);

            return $row;
        });

        return [
            'night' => $night,
            'in_window' => $this->isOvernightWindow(),
            'approved' => $rows->filter(fn (array $row) => $row['request'] !== null)->values(),
            'unapproved' => $rows->filter(fn (array $row) => $row['request'] === null)->values(),
        ];
    }
}
