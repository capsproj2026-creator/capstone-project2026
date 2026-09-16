<?php

namespace App\Http\Controllers;

use App\Services\Sync\AtlasSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Backend-verified online/offline indicator for local-first laptops.
 * Deliberately does NOT rely on navigator.onLine (a laptop can have Wi-Fi
 * but no route to Atlas, or vice versa) — status is based on the same
 * short-timeout, cached ping AtlasSyncService uses for the scheduler.
 */
class SyncStatusController extends Controller
{
    public function status(AtlasSyncService $sync): JsonResponse
    {
        if (! $sync->enabled()) {
            // Cloud/Atlas-only deployment or plain local dev: sync feature
            // is off, so there is nothing meaningful to show — the caller
            // should hide the banner entirely in this case.
            return response()->json(['enabled' => false]);
        }

        return response()->json([
            'enabled' => true,
            'device_id' => (string) config('sync.device_id', ''),
            'atlas_configured' => $sync->atlasConfigured(),
            'atlas_reachable' => $sync->isAtlasReachable(),
        ]);
    }
}
