<?php

namespace App\Services;

use App\Models\User;

/**
 * Temporary gate access for users in declined_remedial registration state.
 */
class RemedialRfidService
{
    public const GRANT_REASON = 'Remedial access — registration declined pending document correction';

    public const EXPIRED_MESSAGE = 'Remedial access expired. Sign in and resubmit your documents to restore campus entry.';

    public const ONE_TIME_MESSAGE = 'This remedial pass was one-time only. Resubmit your documents to re-enter campus.';

    public const EXIT_ONLY_AFTER_ENTRY = 'Exit is allowed after a remedial entry. Resubmit documents for a new visit.';

    public const GATE_DISABLED_MESSAGE = 'Remedial gate access is disabled. Sign in and resubmit your documents.';

    public function enabled(): bool
    {
        return (bool) config('services.registration.remedial_gate_enabled', true);
    }

    public function hours(): int
    {
        $hours = (int) config('services.registration.remedial_hours', 0);

        if ($hours > 0) {
            return $hours;
        }

        return max(1, (int) config('services.rfid.temp_access_hours', 5));
    }

    public function oneEntryOnly(): bool
    {
        return (bool) config('services.registration.remedial_one_entry', true);
    }

    public function expiresAtForNewDecline(): \Illuminate\Support\Carbon
    {
        return now()->addHours($this->hours());
    }

    public function canUseGate(User $user): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if (! $user->isRemedialDeclined()) {
            return false;
        }

        if (! (bool) ($user->remedial_gate_enabled ?? false)) {
            return false;
        }

        return ! $user->remedialAccessExpired();
    }
}
