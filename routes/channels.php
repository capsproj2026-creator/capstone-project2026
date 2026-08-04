<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('gate.scans', function ($user) {
    if (! $user) {
        return false;
    }

    // Prefer role ids (Admin=1, Guard=2) so auth works even if the role relation is unloaded.
    if (in_array((int) ($user->user_role_id ?? 0), [1, 2], true)) {
        return true;
    }

    return in_array($user->roleName(), ['Admin', 'Guard'], true);
});
