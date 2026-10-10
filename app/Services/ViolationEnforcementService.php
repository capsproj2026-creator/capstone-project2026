<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\SanctionEndorsement;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\ViolationLog;
use App\Notifications\AccountLockedNotification;
use App\Notifications\ParkingPermitSuspendedNotification;
use InvalidArgumentException;

/**
 * CSPC Ref. No. 2026-021 offense ladder:
 *   1st offense — warning ticket only.
 *   2nd offense — Security endorses to the GSU; once the GSU approves, the parking permit is suspended for six months.
 *   3rd offense — the GSU verifies and endorses to the VPAF; once the VPAF approves, parking privileges are revoked.
 */
class ViolationEnforcementService
{
    public const MAX_STRIKES = 3;

    public const SUSPENSION_MONTHS = 6;

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

        $this->applyStrikes($user, $logCount, null, null);
    }

    /**
     * Set strike_count from the number of violation logs and open any endorsement the new count calls for.
     */
    public function syncStrikesFromLogs(User $user, ?ViolationLog $latest = null, ?string $endorsedBy = null): int
    {
        $strikes = ViolationLog::query()->where('user_id', $user->id)->count();

        $this->applyStrikes($user, $strikes, $latest, $endorsedBy);

        return $strikes;
    }

    public function endorsementsEnabled(): bool
    {
        return app(SystemSettingService::class)->bool('auto_lock_on_3rd_violation', true);
    }

    private function applyStrikes(User $user, int $strikes, ?ViolationLog $latest, ?string $endorsedBy): void
    {
        $user->update(['strike_count' => $strikes]);

        if ($strikes < self::MAX_STRIKES) {
            $this->closeLevel($user, 3);

            // Accounts locked by the old automatic 3-strike rule reopen when their citations drop below three.
            if ($user->status === User::STATUS_LOCKED) {
                $user->update([
                    'status' => User::STATUS_GRANTED,
                    'Gate_access' => User::GATE_ACCESS_GRANTED,
                ]);
            }
        }

        if ($strikes < 2) {
            $this->closeLevel($user, 2);
        }

        if (! $this->endorsementsEnabled() || $user->status === User::STATUS_LOCKED) {
            return;
        }

        if ($strikes >= 2) {
            $this->ensureEndorsement($user, 2, $latest, $endorsedBy);
        }

        if ($strikes >= self::MAX_STRIKES) {
            $this->ensureEndorsement($user, 3, $latest, $endorsedBy);
        }
    }

    private function ensureEndorsement(User $user, int $level, ?ViolationLog $latest, ?string $endorsedBy): void
    {
        $exists = SanctionEndorsement::query()
            ->where('user_id', $user->id)
            ->where('offense_level', $level)
            ->whereIn('status', [
                SanctionEndorsement::STATUS_PENDING_GSU,
                SanctionEndorsement::STATUS_PENDING_VPAF,
                SanctionEndorsement::STATUS_APPROVED,
                SanctionEndorsement::STATUS_REJECTED,
            ])
            ->exists();

        if ($exists) {
            return;
        }

        SanctionEndorsement::query()->create([
            'user_id' => $user->id,
            'offense_level' => $level,
            'violation_log_id' => $latest ? (string) $latest->getKey() : null,
            'plate_number' => $latest?->plate_number ?? $user->plate_number,
            'status' => SanctionEndorsement::STATUS_PENDING_GSU,
            'endorsed_by' => $endorsedBy,
            'created_at' => now(),
        ]);
    }

    /**
     * Withdraw a level's endorsements and lift its sanction (used when citations are removed).
     */
    private function closeLevel(User $user, int $level): void
    {
        $endorsements = SanctionEndorsement::query()
            ->where('user_id', $user->id)
            ->where('offense_level', $level)
            ->whereIn('status', [
                SanctionEndorsement::STATUS_PENDING_GSU,
                SanctionEndorsement::STATUS_PENDING_VPAF,
                SanctionEndorsement::STATUS_APPROVED,
            ])
            ->get();

        foreach ($endorsements as $endorsement) {
            $endorsement->update([
                'status' => SanctionEndorsement::STATUS_WITHDRAWN,
                'closed_at' => now(),
            ]);
        }

        $kind = $level >= 3 ? UserSuspension::KIND_REVOCATION : UserSuspension::KIND_SUSPENSION;

        UserSuspension::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (UserSuspension $row) => ($row->kind ?? ($row->suspended_until === null ? UserSuspension::KIND_REVOCATION : UserSuspension::KIND_SUSPENSION)) === $kind)
            ->each(fn (UserSuspension $row) => $row->delete());
        $user->forgetParkingSanction();
    }

    /**
     * GSU decision on a 2nd-offense endorsement (approve = six-month suspension).
     */
    public function gsuDecideSecondOffense(SanctionEndorsement $endorsement, bool $approve, int $reviewerId, ?string $remarks): void
    {
        $this->assertStatus($endorsement, 2, SanctionEndorsement::STATUS_PENDING_GSU);

        $user = $endorsement->user;
        $from = now();
        $until = $from->copy()->addMonthsNoOverflow(self::SUSPENSION_MONTHS);

        $endorsement->update([
            'status' => $approve ? SanctionEndorsement::STATUS_APPROVED : SanctionEndorsement::STATUS_REJECTED,
            'gsu_reviewed_by' => $reviewerId,
            'gsu_reviewed_at' => now(),
            'gsu_remarks' => $remarks,
            'effective_from' => $approve ? $from : null,
            'effective_until' => $approve ? $until : null,
            'closed_at' => now(),
        ]);

        if (! $user) {
            return;
        }

        if (! $approve) {
            $this->notify($user, '2nd Offense: Suspension Not Approved',
                'The GSU reviewed your 2nd offense and did not approve a parking permit suspension.'
                .($remarks ? ' Remarks: '.$remarks : ''));

            return;
        }

        $existing = $user->activeParkingSanction();
        if ($existing && $existing->isRevocation()) {
            return;
        }

        UserSuspension::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'strike_count' => (int) ($user->strike_count ?? 0),
                'is_suspended' => true,
                'kind' => UserSuspension::KIND_SUSPENSION,
                'starts_at' => $from,
                'suspended_until' => $until,
                'endorsement_id' => (int) $endorsement->id,
            ]
        );
        $user->forgetParkingSanction();

        $this->notify($user, '2nd Offense: Parking Permit Suspended',
            'The GSU approved a six-month suspension of your parking permit. You cannot enter campus with your vehicle until '
            .ph_date($until, 'M j, Y').'.'.($remarks ? ' Remarks: '.$remarks : ''));
        $this->mail($user, new ParkingPermitSuspendedNotification($until));
    }

    /**
     * GSU decision on a 3rd-offense endorsement (approve = forward to the VPAF).
     */
    public function gsuDecideThirdOffense(SanctionEndorsement $endorsement, bool $endorseToVpaf, int $reviewerId, ?string $remarks): void
    {
        $this->assertStatus($endorsement, 3, SanctionEndorsement::STATUS_PENDING_GSU);

        $endorsement->update([
            'status' => $endorseToVpaf ? SanctionEndorsement::STATUS_PENDING_VPAF : SanctionEndorsement::STATUS_REJECTED,
            'gsu_reviewed_by' => $reviewerId,
            'gsu_reviewed_at' => now(),
            'gsu_remarks' => $remarks,
            'closed_at' => $endorseToVpaf ? null : now(),
        ]);

        if ($endorsement->user && ! $endorseToVpaf) {
            $this->notify($endorsement->user, '3rd Offense: Not Endorsed',
                'The GSU reviewed your 3rd offense and did not endorse it to the VPAF.'
                .($remarks ? ' Remarks: '.$remarks : ''));
        }
    }

    /**
     * VPAF decision recorded on a 3rd-offense endorsement (approve = revoke parking privileges).
     */
    public function vpafDecideThirdOffense(SanctionEndorsement $endorsement, bool $approve, int $reviewerId, ?string $remarks): void
    {
        $this->assertStatus($endorsement, 3, SanctionEndorsement::STATUS_PENDING_VPAF);

        $endorsement->update([
            'status' => $approve ? SanctionEndorsement::STATUS_APPROVED : SanctionEndorsement::STATUS_REJECTED,
            'vpaf_reviewed_by' => $reviewerId,
            'vpaf_reviewed_at' => now(),
            'vpaf_remarks' => $remarks,
            'effective_from' => $approve ? now() : null,
            'closed_at' => now(),
        ]);

        $user = $endorsement->user;
        if (! $user) {
            return;
        }

        if (! $approve) {
            $this->notify($user, '3rd Offense: Revocation Not Approved',
                'The VPAF did not approve revoking your parking privileges.'.($remarks ? ' Remarks: '.$remarks : ''));

            return;
        }

        UserSuspension::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'strike_count' => (int) ($user->strike_count ?? 0),
                'is_suspended' => true,
                'kind' => UserSuspension::KIND_REVOCATION,
                'starts_at' => now(),
                'suspended_until' => null,
                'endorsement_id' => (int) $endorsement->id,
            ]
        );
        $user->forgetParkingSanction();

        $this->notify($user, '3rd Offense: Parking Privileges Revoked',
            'The VPAF approved the revocation of your campus parking privileges. You can no longer enter campus with your vehicle.'
            .($remarks ? ' Remarks: '.$remarks : ''));
        $this->mail($user, new AccountLockedNotification((int) ($user->strike_count ?? 0)));
    }

    private function assertStatus(SanctionEndorsement $endorsement, int $level, string $status): void
    {
        if ((int) $endorsement->offense_level !== $level || $endorsement->status !== $status) {
            throw new InvalidArgumentException('This endorsement has already been decided.');
        }
    }

    private function notify(User $user, string $title, string $message): void
    {
        if (! app(SystemSettingService::class)->bool('send_violation_notifications', true)) {
            return;
        }

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'title' => $title,
            'message' => $message,
            'type' => 'Violation',
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    private function mail(User $user, \Illuminate\Notifications\Notification $notification): void
    {
        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === '' || str_ends_with($email, '.invalid')) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
