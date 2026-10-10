<?php

namespace App\Support;

use App\Models\UserSuspension;

class ParkingSanctions
{
    /**
     * Users with a GSU-approved suspension or VPAF-approved revocation in force.
     *
     * @return list<int>
     */
    public static function activeUserIds(): array
    {
        return UserSuspension::query()
            ->where('is_suspended', true)
            ->get(['user_id', 'is_suspended', 'suspended_until'])
            ->filter(fn (UserSuspension $row) => $row->isActive())
            ->map(fn (UserSuspension $row) => (int) $row->user_id)
            ->unique()
            ->values()
            ->all();
    }
}
