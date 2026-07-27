<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSuspension;
use App\Models\ViolationLog;
use App\Services\SystemSettingService;

class ViolationEnforcementService
{
    public const MAX_STRIKES = 3;

    /**
     * Sync strike_count from violation logs (fixes records logged before enforcement was wired).
     */
    public function reconcileFromViolationHistory(User $user): void
    {
        $logCount = ViolationLog::query()->where('user_id', $user->id)->count();
        $strikes = (int) ($user->strike_count ?? 0);

        if ($logCount <= $strikes) {
            return;
        }

        $this->setStrikeCount($user, $logCount);
    }

    /**
     * Set strike_count from the number of violation logs for this user.
     */
    public function syncStrikesFromLogs(User $user): int
    {
        $strikes = ViolationLog::query()->where('user_id', $user->id)->count();

        $this->setStrikeCount($user, $strikes);

        return $strikes;
    }

    private function setStrikeCount(User $user, int $strikes): void
    {
        $updates = ['strike_count' => $strikes];

        $autoLock = app(SystemSettingService::class)->bool('auto_lock_on_3rd_violation', true);

        if ($autoLock && $strikes >= self::MAX_STRIKES) {
            $updates['status'] = User::STATUS_LOCKED;
            $updates['Gate_access'] = User::GATE_ACCESS_DENIED;
        }

        $user->update($updates);

        if ($autoLock && $strikes >= self::MAX_STRIKES) {
            $this->recordPermanentSuspension($user, $strikes);
        }
    }

    private function recordPermanentSuspension(User $user, int $strikes): void
    {
        UserSuspension::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'strike_count' => $strikes,
                'is_suspended' => true,
                'suspended_until' => null,
            ]
        );
    }
}
