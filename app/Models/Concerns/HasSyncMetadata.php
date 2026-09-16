<?php

namespace App\Models\Concerns;

/**
 * Opt-in trait for models listed in config('sync.collections'). Stamps each
 * document with enough metadata for App\Services\Sync\AtlasSyncService to
 * push/pull it safely, without ever exposing these fields to normal app
 * code paths that do not ask for them.
 *
 * Completely inert when SYNC_ENABLED is false (the current production /
 * cloud-only / plain local-dev default) — no new fields are written and
 * existing documents are untouched.
 *
 * Fields written:
 * - sync_status:       pending | synced | failed | conflict
 * - source_device_id:  which laptop/device created or last changed the row
 * - sync_version:      increments on every save, used for last-write-wins
 * - synced_at:         timestamp of the last successful push/pull
 */
trait HasSyncMetadata
{
    /** @var bool Set true only while AtlasSyncService is applying a pulled document. */
    public static bool $applyingRemoteSync = false;

    public static function bootHasSyncMetadata(): void
    {
        static::creating(function ($model) {
            if (! (bool) config('sync.enabled', false) || static::$applyingRemoteSync) {
                return;
            }

            if ($model->getAttribute('sync_status') === null) {
                $model->setAttribute('sync_status', 'pending');
            }
            if ($model->getAttribute('source_device_id') === null) {
                $model->setAttribute('source_device_id', (string) config('sync.device_id', ''));
            }
            if ($model->getAttribute('sync_version') === null) {
                $model->setAttribute('sync_version', 1);
            }
        });

        static::updating(function ($model) {
            if (! (bool) config('sync.enabled', false) || static::$applyingRemoteSync) {
                return;
            }

            // A genuine local edit means this row needs to go out again,
            // even if it was previously synced.
            $model->setAttribute('sync_status', 'pending');
            $model->setAttribute('sync_version', (int) ($model->getAttribute('sync_version') ?? 1) + 1);
        });
    }

    /**
     * Run $callback while flagging writes as remote-sync-applied, so the
     * creating/updating hooks above do not immediately re-mark the just-
     * pulled document as 'pending' again.
     */
    public static function withoutSyncStamping(callable $callback): mixed
    {
        $previous = static::$applyingRemoteSync;
        static::$applyingRemoteSync = true;

        try {
            return $callback();
        } finally {
            static::$applyingRemoteSync = $previous;
        }
    }
}
