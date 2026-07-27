<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\Operation\FindOneAndUpdate;

class SequenceService
{
    public static function next(string $collection): int
    {
        $counters = DB::connection('mongodb')->getCollection('counters');
        $targetCollection = DB::connection('mongodb')->getCollection($collection);

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
