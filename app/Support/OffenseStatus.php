<?php

namespace App\Support;

use App\Models\SanctionEndorsement;
use App\Models\User;
use App\Models\UserSuspension;
use Illuminate\Support\Collection;

/**
 * Where a user stands on the 1st → 2nd → 3rd offense ladder.
 *
 * @phpstan-type Status array{label: string, detail: string, tone: 'red'|'orange'|'amber'|'gray', icon: string}
 */
class OffenseStatus
{
    /**
     * @return Status|null
     */
    public static function for(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return self::forUsers(collect([$user]))[(int) $user->id] ?? null;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, Status>
     */
    public static function forUsers(Collection $users): array
    {
        $users = $users->filter()->unique(fn (User $u) => (int) $u->id)->values();
        $ids = $users->map(fn (User $u) => (int) $u->id)->all();

        if ($ids === []) {
            return [];
        }

        $sanctions = UserSuspension::query()
            ->whereIn('user_id', $ids)
            ->where('is_suspended', true)
            ->get()
            ->filter(fn (UserSuspension $row) => $row->isActive())
            ->keyBy(fn (UserSuspension $row) => (int) $row->user_id);

        $endorsements = SanctionEndorsement::query()
            ->whereIn('user_id', $ids)
            ->whereIn('status', [
                SanctionEndorsement::STATUS_PENDING_GSU,
                SanctionEndorsement::STATUS_PENDING_VPAF,
                SanctionEndorsement::STATUS_REJECTED,
            ])
            ->get()
            ->groupBy(fn (SanctionEndorsement $row) => (int) $row->user_id);

        $out = [];
        foreach ($users as $user) {
            $status = self::resolve(
                $user,
                $sanctions->get((int) $user->id),
                $endorsements->get((int) $user->id, collect())
            );
            if ($status) {
                $out[(int) $user->id] = $status;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, SanctionEndorsement>  $endorsements
     * @return Status|null
     */
    private static function resolve(User $user, ?UserSuspension $sanction, Collection $endorsements): ?array
    {
        if ($sanction?->isRevocation()) {
            return [
                'label' => 'Parking privileges revoked',
                'detail' => '3rd offense approved by the VPAF. The vehicle cannot enter campus.',
                'tone' => 'red',
                'icon' => 'ban',
            ];
        }

        if ($sanction) {
            return [
                'label' => 'Parking permit suspended',
                'detail' => '2nd offense approved by the GSU. Suspended until '.ph_date($sanction->suspended_until, 'M j, Y').'.',
                'tone' => 'red',
                'icon' => 'lock',
            ];
        }

        if ($user->isLocked()) {
            return [
                'label' => 'Account locked',
                'detail' => 'Locked before the GSU endorsement process. Remove citations or contact the GSU to reopen.',
                'tone' => 'red',
                'icon' => 'lock',
            ];
        }

        $open = $endorsements
            ->filter(fn (SanctionEndorsement $row) => $row->isOpen())
            ->sortByDesc('offense_level')
            ->first();

        if ($open) {
            return [
                'label' => $open->offenseLabel().' — '.$open->statusLabel(),
                'detail' => $open->sanctionLabel().' is pending. Nothing changes until it is approved.',
                'tone' => 'orange',
                'icon' => 'scale',
            ];
        }

        $strikes = (int) ($user->strike_count ?? 0);

        if ($strikes <= 0) {
            return null;
        }

        if ($strikes === 1) {
            return [
                'label' => '1st Offense — warning ticket',
                'detail' => 'Warning only. No sanction applies.',
                'tone' => 'amber',
                'icon' => 'alert-triangle',
            ];
        }

        $rejected = $endorsements->sortByDesc('offense_level')->first();

        return [
            'label' => 'No active sanction',
            'detail' => $rejected
                ? $rejected->offenseLabel().' endorsement was not approved.'
                : 'Offense endorsements are turned off in Settings.',
            'tone' => 'gray',
            'icon' => 'shield',
        ];
    }

    public static function toneClasses(string $tone): string
    {
        return match ($tone) {
            'red' => 'bg-red-50 text-red-700 border-red-200',
            'orange' => 'bg-orange-50 text-orange-700 border-orange-200',
            'amber' => 'bg-amber-50 text-amber-800 border-amber-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    }
}
