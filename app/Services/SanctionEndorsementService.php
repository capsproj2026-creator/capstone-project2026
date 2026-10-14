<?php

namespace App\Services;

use App\Models\SanctionEndorsement;
use App\Models\User;
use App\Models\UserSuspension;
use App\Notifications\AccountLockedNotification;

class SanctionEndorsementService
{
    public function __construct(private readonly RegistrationAlertService $alerts) {}

    public function syncForStrikes(User $user, int $strikes): void
    {
        if ($strikes >= User::MAX_STRIKES) {
            $this->ensurePending($user, $strikes, SanctionEndorsement::KIND_REVOCATION);
        } elseif ($strikes === 2) {
            $this->ensurePending($user, $strikes, SanctionEndorsement::KIND_SUSPENSION);
        }

        if ($strikes < User::MAX_STRIKES) {
            $this->cancelPending($user, SanctionEndorsement::KIND_REVOCATION);
        }

        if ($strikes < 2) {
            $this->cancelPending($user, SanctionEndorsement::KIND_SUSPENSION);
        }
    }

    public function endorse(SanctionEndorsement $endorsement, User $gsu, string $reason): void
    {
        if ($endorsement->status !== SanctionEndorsement::STATUS_PENDING_GSU) {
            return;
        }

        $endorsement->update([
            'status' => SanctionEndorsement::STATUS_PENDING_VPAF,
            'reason' => $reason,
            'gsu_id' => $gsu->id,
            'endorsed_at' => now(),
        ]);

        $owner = $endorsement->user;
        $name = $owner?->displayName() ?? 'A vehicle owner';

        $this->alerts->notifyRole(
            NavigationService::ROLE_VPAF,
            $gsu->id,
            'Endorsement waiting for approval',
            "GSU endorsed a {$endorsement->label()} for {$name}. Review it in the VPAF queue.",
        );
    }

    public function decide(SanctionEndorsement $endorsement, User $vpaf, bool $approve, ?string $note = null): void
    {
        if ($endorsement->status !== SanctionEndorsement::STATUS_PENDING_VPAF) {
            return;
        }

        $endorsement->update([
            'status' => $approve ? SanctionEndorsement::STATUS_APPROVED : SanctionEndorsement::STATUS_REJECTED,
            'vpaf_id' => $vpaf->id,
            'decided_at' => now(),
            'reason' => filled($note) ? trim((string) $note) : $endorsement->reason,
        ]);

        $owner = $endorsement->user;
        if (! $owner) {
            return;
        }

        if (! $approve) {
            $this->alerts->notifyAdmins(
                $owner,
                'VPAF rejected an endorsement',
                "VPAF rejected the {$endorsement->label()} for {$owner->displayName()}. The citation stays on record.",
            );

            return;
        }

        if ($endorsement->kind === SanctionEndorsement::KIND_REVOCATION) {
            $owner->update([
                'status' => User::STATUS_LOCKED,
                'Gate_access' => User::GATE_ACCESS_DENIED,
            ]);
            UserSuspension::query()->updateOrCreate(
                ['user_id' => $owner->id],
                [
                    'strike_count' => (int) $endorsement->strike_number,
                    'is_suspended' => true,
                    'suspended_until' => null,
                    'kind' => SanctionEndorsement::KIND_REVOCATION,
                    'endorsement_id' => $endorsement->id,
                ]
            );
            $owner->forgetParkingSanction();

            try {
                $owner->notify(new AccountLockedNotification((int) $endorsement->strike_number));
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        UserSuspension::query()->updateOrCreate(
            ['user_id' => $owner->id],
            [
                'strike_count' => (int) $endorsement->strike_number,
                'is_suspended' => true,
                'suspended_until' => now()->addMonths(6),
                'kind' => SanctionEndorsement::KIND_SUSPENSION,
                'endorsement_id' => $endorsement->id,
            ]
        );
        $owner->forgetParkingSanction();
    }

    private function ensurePending(User $user, int $strikes, string $kind): void
    {
        $latest = SanctionEndorsement::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->orderByDesc('id')
            ->first();

        if ($latest && in_array($latest->status, [
            SanctionEndorsement::STATUS_PENDING_GSU,
            SanctionEndorsement::STATUS_PENDING_VPAF,
            SanctionEndorsement::STATUS_APPROVED,
        ], true)) {
            return;
        }

        if ($latest && $latest->status === SanctionEndorsement::STATUS_REJECTED
            && (int) $latest->strike_number >= $strikes) {
            return;
        }

        SanctionEndorsement::query()->create([
            'user_id' => $user->id,
            'strike_number' => $strikes,
            'kind' => $kind,
            'status' => SanctionEndorsement::STATUS_PENDING_GSU,
            'created_at' => now(),
        ]);

        $label = $kind === SanctionEndorsement::KIND_REVOCATION
            ? 'revocation of parking privileges'
            : 'six-month parking suspension';

        $this->alerts->notifyAdmins(
            $user,
            'Sanction needs GSU endorsement',
            "{$user->displayName()} reached {$strikes} strikes. Endorse the {$label} so VPAF can approve it.",
        );
    }

    private function cancelPending(User $user, string $kind): void
    {
        SanctionEndorsement::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->whereIn('status', [
                SanctionEndorsement::STATUS_PENDING_GSU,
                SanctionEndorsement::STATUS_PENDING_VPAF,
            ])
            ->delete();
    }
}
