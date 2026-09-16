<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\Operation\FindOneAndUpdate;

class SequenceService
{
    /**
     * Private numeric id block for this device, so ids generated locally on
     * one laptop can never collide with ids generated on another laptop or
     * on Atlas. Returns 0 (no offset — exact legacy behavior) unless
     * SYNC_ENABLED=true and this device has a registered SYNC_DEVICE_ID.
     *
     * Mongo's `_id` here IS the app's sequential integer (verified: this app
     * sets $primaryKey = 'id' with HasSequentialId, and the mongodb/laravel-
     * mongodb package stores that value directly as the document's native
     * `_id` — there is no separate ObjectId to lean on). That makes plain
     * per-collection auto-increment unsafe for multi-device sync, so instead
     * of introducing a UUID (which would touch every relation/foreign key/
     * route-model-binding in the app), each device gets its own reserved
     * block of the same integer id space. Ids stay plain integers; nothing
     * else in the app has to change.
     */
    public static function deviceOffset(): int
    {
        if (! (bool) config('sync.enabled', false)) {
            return 0;
        }

        $deviceId = (string) config('sync.device_id', '');
        if ($deviceId === '') {
            return 0;
        }

        $registry = (array) config('sync.device_registry', []);
        $index = (int) ($registry[$deviceId] ?? 0);
        if ($index <= 0) {
            return 0;
        }

        return $index * (int) config('sync.device_offset_block', 10_000_000);
    }

    public static function next(string $collection): int
    {
        $counters = DB::connection('mongodb')->getCollection('counters');
        $targetCollection = DB::connection('mongodb')->getCollection($collection);
        $floor = self::deviceOffset();

        $maxDocs = $targetCollection->aggregate([
            [
                '$group' => [
                    '_id' => null,
                    'maxId' => [
                        '$max' => [
                            '$ifNull' => ['$id', '$_id'],
                        ],
                    ],
                ],
            ],
        ])->toArray();

        $maxId = (int) ($maxDocs[0]['maxId'] ?? 0);
        // Never let this device's counter drift below its reserved block,
        // even the very first time it runs against an empty local database.
        $maxId = max($maxId, $floor);

        // Step 1: Ensure the counter document exists.
        $counters->updateOne(
            ['_id' => $collection],
            ['$setOnInsert' => ['seq' => $maxId]],
            ['upsert' => true]
        );

        // Step 2: If counter is behind existing data, bump it up (separate from $inc).
        if ($maxId > 0) {
            $counters->updateOne(
                ['_id' => $collection, 'seq' => ['$lt' => $maxId]],
                ['$set' => ['seq' => $maxId]]
            );
        }

        // Step 3: Atomically increment and return the next id.
        $result = $counters->findOneAndUpdate(
            ['_id' => $collection],
            ['$inc' => ['seq' => 1]],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        return (int) ($result['seq'] ?? ($maxId + 1));
    }

    /**
     * Align counter documents with the highest numeric id in each collection.
     *
     * @param  list<class-string>  $modelClasses
     */
    public static function syncCountersForModels(array $modelClasses): void
    {
        $counters = DB::connection('mongodb')->getCollection('counters');

        foreach ($modelClasses as $modelClass) {
            $collection = (new $modelClass)->getTable();
            $maxId = $modelClass::query()->max('id');

            $counters->updateOne(
                ['_id' => $collection],
                ['$set' => ['seq' => (int) ($maxId ?? 0)]],
                ['upsert' => true]
            );
        }
    }
}
