<?php

/**
 * Local-first <-> MongoDB Atlas synchronization settings.
 *
 * Defaults are 100% inert: SYNC_ENABLED=false (the .env.example / current
 * production default) makes every helper in App\Services\Sync a no-op and
 * SequenceService::next() behaves exactly as it did before this feature
 * existed. Nothing here changes behavior unless a laptop explicitly opts in.
 */
return [

    // Master switch. Cloud/Atlas-only deployments and the existing single-DB
    // local dev setup should leave this false — the app then behaves exactly
    // as it did before local-first sync existed.
    'enabled' => filter_var(env('SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Stable identifier for this machine. Must NOT be an IP address and must
    // stay the same across restarts. Example: LAPTOP-01, LAPTOP-02, LAPTOP-03.
    'device_id' => trim((string) env('SYNC_DEVICE_ID', '')),

    // Registry mapping known device ids to a small integer index. The index
    // is only used to compute a private id block (see SequenceService) so
    // two laptops never hand out the same numeric id. Add more entries here
    // if a 4th/5th laptop is introduced — each needs a unique index.
    'device_registry' => [
        'LAPTOP-01' => 1,
        'LAPTOP-02' => 2,
        'LAPTOP-03' => 3,
    ],

    // Number of ids reserved per device (10 million is far beyond anything
    // this system will ever create locally between syncs).
    'device_offset_block' => 10_000_000,

    // How long an "Atlas reachable?" check is cached for, so page requests
    // and the scheduler never block on a slow/unreachable network socket.
    'atlas_ping_cache_seconds' => 20,
    'atlas_ping_timeout_ms' => 2000,

    // Max documents pushed/pulled per collection per sync run. Keeps each
    // scheduler tick fast and bounded instead of one giant transfer.
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 200),

    // Eloquent model classes that participate in local <-> Atlas sync, and
    // how conflicts on that collection should be handled:
    // - 'append_only'  => never updated after creation (safe: upsert by id,
    //                     no conflict logic needed).
    // - 'last_write'    => can be edited on more than one device; last
    //                     sync_version/updated_at wins, true conflicts
    //                     (edited on two devices since the last sync) are
    //                     flagged sync_status=conflict instead of silently
    //                     overwritten.
    'collections' => [
        \App\Models\User::class => 'last_write',
        \App\Models\Visitor::class => 'last_write',
        \App\Models\GateLog::class => 'append_only',
        \App\Models\ViolationLog::class => 'append_only',
        \App\Models\VisitorRfidCard::class => 'last_write',
    ],
];
