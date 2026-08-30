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
            ->get()
            ->first(function (User $user) {
                if ($user->isRemedialDeclined()) {
                    return true;
                }

                return $user->isFinalDenied() || $user->registrationState() === User::REGISTRATION_DENIED_FINAL;
            });
    }

    public static function findActiveRemedialUser(string $email, string $idNumber): ?User
    {
        return User::query()
            ->where(function ($q) use ($email, $idNumber) {
                $q->where('email', $email)->orWhere('id_number', $idNumber);
            })
            ->get()
            ->first(fn (User $user) => $user->isRemedialDeclined() && ! $user->remedialAccessExpired());
    }

    public static function cooldownEndsAt(User $user): ?CarbonInterface
    {
        if ($user->isRemedialDeclined()) {
            return null;
        }

        $declinedAt = $user->declined_at;
        if (! $declinedAt) {
            return null;
        }

        return $declinedAt->copy()->addDays(self::DAYS);
    }

    public static function isWithinCooldown(User $user): bool
    {
        if ($user->isRemedialDeclined()) {
            return true;
        }

        $ends = self::cooldownEndsAt($user);
        if (! $ends) {
            return false;
        }

        return now()->lt($ends);
    }

    public static function remainingMessage(User $user): string
    {
        if ($user->isRemedialDeclined()) {
            return 'Your registration needs document correction. Sign in with your existing account to upload corrected documents and resubmit for review.';
        }

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
     * Remove expired final-denied accounts that collide on email/id so a new registration can proceed.
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
            if ($user->isRemedialDeclined()) {
                continue;
            }

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
