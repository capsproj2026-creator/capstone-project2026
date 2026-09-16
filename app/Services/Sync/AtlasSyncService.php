<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Local-first <-> MongoDB Atlas bridge.
 *
 * Design notes (see config/sync.php for the switches):
 * - The app's default 'mongodb' connection is untouched by this service —
 *   whatever it points to (local Mongo on a laptop, Atlas directly on the
 *   cloud deployment) keeps working exactly as before. This service only
 *   ever talks to the SEPARATE 'mongodb_atlas' connection in addition to it.
 * - All reads/writes here go through the raw MongoDB collection driver
 *   (not Eloquent) for both directions, so pulling a document from Atlas
 *   into the local database can never accidentally re-run model mutators
 *   (e.g. re-hashing an already-hashed password) or re-fire creating/
 *   updating hooks — it is a plain, idempotent upsert-by-id.
 * - Because App\Models\Concerns\HasSequentialId writes the app's sequential
 *   integer directly as Mongo's native `_id` (there is no separate ObjectId
 *   in this schema), plain upsert-by-`_id` is dedup-safe: running sync
 *   repeatedly never creates duplicate documents (TEST 8).
 * - Append-only collections (GateLog, ViolationLog) are never updated after
 *   creation by the app, so they only ever need a one-way "insert if new"
 *   upsert — no conflict logic applies to them.
 * - "last_write" collections (User, Visitor, VisitorRfidCard) can be edited
 *   on more than one device. A push is only allowed to overwrite Atlas when
 *   Atlas's own sync_version for that id is not ahead of what this device
 *   last saw; otherwise the local row is flagged sync_status=conflict and
 *   left alone rather than silently destroying the other device's edit.
 */
class AtlasSyncService
{
    public function enabled(): bool
    {
        return (bool) config('sync.enabled', false)
            && trim((string) config('sync.device_id', '')) !== '';
    }

    public function atlasConfigured(): bool
    {
        return trim((string) config('database.connections.mongodb_atlas.dsn', '')) !== '';
    }

    /**
     * Is Atlas reachable right now? Cached briefly and with a short server-
     * selection timeout (config('sync.atlas_ping_timeout_ms')) so this never
     * blocks a normal web request or a scheduler tick for long.
     */
    public function isAtlasReachable(): bool
    {
        if (! $this->atlasConfigured()) {
            return false;
        }

        $ttl = (int) config('sync.atlas_ping_cache_seconds', 20);

        return (bool) Cache::remember('sync:atlas_reachable', now()->addSeconds($ttl), function () {
            try {
                DB::connection('mongodb_atlas')
                    ->getClient()
                    ->selectDatabase((string) config('database.connections.mongodb_atlas.database'))
                    ->command(['ping' => 1]);

                return true;
            } catch (Throwable $e) {
                return false;
            }
        });
    }

    /**
     * @return array<string, mixed> per-collection push/pull counts, or a
     *                              single 'skipped' key with the reason.
     */
    public function syncAll(): array
    {
        if (! $this->enabled()) {
            return ['skipped' => 'SYNC_ENABLED is false or SYNC_DEVICE_ID is empty'];
        }

        if (! $this->atlasConfigured()) {
            return ['skipped' => 'MONGODB_ATLAS_URI is not configured'];
        }

        if (! $this->isAtlasReachable()) {
            return ['skipped' => 'Atlas is not reachable right now'];
        }

        $summary = [];
        foreach ((array) config('sync.collections', []) as $modelClass => $strategy) {
            try {
                $summary[$modelClass] = $this->syncCollection($modelClass, (string) $strategy);
            } catch (Throwable $e) {
                report($e);
                Log::warning("[sync] {$modelClass} failed: {$e->getMessage()}");
                $summary[$modelClass] = ['error' => $e->getMessage()];
            }
        }

        return $summary;
    }

    /**
     * @return array{pushed: int, conflicts: int, pulled: int}
     */
    private function syncCollection(string $modelClass, string $strategy): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $deviceId = (string) config('sync.device_id', '');
        $batch = max(1, (int) config('sync.batch_size', 200));

        $local = DB::connection('mongodb')->getCollection($table);
        $atlas = DB::connection('mongodb_atlas')->getCollection($table);

        // --- PUSH: local sync_status=pending -> Atlas -------------------
        $pending = $local->find(
            ['sync_status' => 'pending'],
            ['limit' => $batch]
        )->toArray();

        $pushed = 0;
        $conflicts = 0;

        foreach ($pending as $doc) {
            $doc = (array) $doc;
            $id = $doc['_id'];

            if ($strategy === 'last_write') {
                $remote = $atlas->findOne(['_id' => $id]);
                $remoteVersion = (int) ($remote['sync_version'] ?? 0);
                $remoteDevice = (string) ($remote['source_device_id'] ?? '');
                $localVersion = (int) ($doc['sync_version'] ?? 1);

                // Someone else's edit is ahead of what we are about to push
                // and we also have a local change pending -> real conflict.
                // Do not overwrite; flag for admin review instead.
                if ($remote && $remoteVersion > $localVersion && $remoteDevice !== '' && $remoteDevice !== $deviceId) {
                    $local->updateOne(['_id' => $id], ['$set' => ['sync_status' => 'conflict']]);
                    $conflicts++;

                    continue;
                }
            }

            $atlas->updateOne(['_id' => $id], ['$set' => $doc], ['upsert' => true]);
            $local->updateOne(['_id' => $id], ['$set' => [
                'sync_status' => 'synced',
                'synced_at' => now()->toIso8601String(),
            ]]);
            $pushed++;
        }

        // --- PULL: Atlas rows from other devices -> local ----------------
        // Only pull rows this device did not itself create, and only ones
        // newer than what we already have (by sync_version) — keeps this
        // idempotent and cheap on repeated runs.
        $remoteRows = $atlas->find(
            [
                'source_device_id' => ['$ne' => $deviceId],
            ],
            ['limit' => $batch, 'sort' => ['synced_at' => -1]]
        )->toArray();

        $pulled = 0;
        foreach ($remoteRows as $doc) {
            $doc = (array) $doc;
            $id = $doc['_id'];
            $existing = $local->findOne(['_id' => $id]);

            if ($existing) {
                $existingVersion = (int) ($existing['sync_version'] ?? 0);
                $incomingVersion = (int) ($doc['sync_version'] ?? 0);
                // Never let a pull clobber a not-yet-pushed local edit or a
                // row already flagged for manual conflict review (that flag
                // is only cleared by an explicit resolution, not a later
                // pull), and never re-apply something we already have.
                if (in_array($existing['sync_status'] ?? null, ['pending', 'conflict'], true) || $incomingVersion <= $existingVersion) {
                    continue;
                }
            }

            $applied = $doc;
            $applied['sync_status'] = 'synced';
            $local->updateOne(['_id' => $id], ['$set' => $applied], ['upsert' => true]);
            $pulled++;
        }

        return ['pushed' => $pushed, 'conflicts' => $conflicts, 'pulled' => $pulled];
    }
}
