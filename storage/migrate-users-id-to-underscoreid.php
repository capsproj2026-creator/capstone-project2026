<?php

/**
 * Migrate sequential numeric id from `id` field into Mongo `_id` for existing users.
 *
 * This project uses MongoDB Laravel's "id => _id alias" behavior in queries.
 * We standardize documents to:
 *   - `_id` = sequential int
 *   - no `id` field (so aliasing works consistently, including auth re-hydration)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');

$cursor = $users->find([
    'id' => ['$exists' => true],
]);

$migrated = 0;
$skipped = 0;

foreach ($cursor as $doc) {
    $docArr = $doc->getArrayCopy();

    if (! array_key_exists('id', $docArr)) {
        $skipped++;
        continue;
    }

    $seqId = $docArr['id'];
    if (is_string($seqId) && ctype_digit($seqId)) {
        $seqId = (int) $seqId;
    }

    // Only migrate when Mongo `_id` is not already the sequential integer.
    $currentId = $docArr['_id'] ?? null;
    $currentIsInt = is_int($currentId);
    if ($currentIsInt) {
        $skipped++;
        continue;
    }

    // Avoid collisions.
    $already = $users->findOne(['_id' => $seqId]);
    if ($already) {
        // If it's the same document, skip; otherwise, refuse to overwrite.
        if (($already['email'] ?? null) === ($docArr['email'] ?? null)) {
            $skipped++;
            continue;
        }

        throw new RuntimeException("Collision: _id={$seqId} already exists with different user.");
    }

    $newDoc = $docArr;
    unset($newDoc['_id']);
    unset($newDoc['id']); // remove the alias field
    $newDoc['_id'] = (int) $seqId;

    // Insert then delete old document.
    $users->insertOne($newDoc);
    $users->deleteOne(['_id' => $docArr['_id']]);

    $migrated++;
}

echo "migrated={$migrated} skipped={$skipped}\n";

