<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

class RegistrationCooldown
{
    public const DAYS = 3;

    public static function findBlockingDeniedUser(string $email, string $idNumber): ?User
    {
        return User::query()
            ->where('status', User::STATUS_DENIED)
            ->where(function ($q) use ($email, $idNumber) {
                $q->where('email', $email)->orWhere('id_number', $idNumber);
            })
            ->orderByDesc('declined_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function cooldownEndsAt(User $user): ?CarbonInterface
    {
        $declinedAt = $user->declined_at;
        if (! $declinedAt) {
            return null;
        }

        return $declinedAt->copy()->addDays(self::DAYS);
    }

    public static function isWithinCooldown(User $user): bool
    {
        $ends = self::cooldownEndsAt($user);
        if (! $ends) {
            // Legacy denied rows without declined_at can re-register immediately once purged.
            return false;
        }

        return now()->lt($ends);
    }

    public static function remainingMessage(User $user): string
    {
        $ends = self::cooldownEndsAt($user);
        if (! $ends) {
            return 'Your previous registration was declined. Please wait '.self::DAYS.' days before registering again.';
        }

        $hours = max(1, (int) ceil(now()->diffInMinutes($ends) / 60));
        if ($hours >= 24) {
            $days = (int) ceil($hours / 24);

            return "Your previous registration was declined. You may register again in {$days} day(s).";
        }

        return "Your previous registration was declined. You may register again in about {$hours} hour(s).";
    }

    /**
     * Remove expired denied accounts that collide on email/id so a new registration can proceed.
     *
     * @return list<User>
     */
    public static function purgeExpiredDeniedCollisions(string $email, string $idNumber): array
    {
        $denied = User::query()
            ->where('status', User::STATUS_DENIED)
            ->where(function ($q) use ($email, $idNumber) {
                $q->where('email', $email)->orWhere('id_number', $idNumber);
            })
            ->get();

        $purged = [];
        foreach ($denied as $user) {
            if (self::isWithinCooldown($user)) {
                continue;
            }
            $purged[] = $user;
            $user->delete();
        }

        return $purged;
    }

    public static function passwordMatchesDenied(User $denied, string $plainPassword): bool
    {
        $hash = (string) ($denied->password ?? '');

        return $hash !== '' && Hash::check($plainPassword, $hash);
    }
}
