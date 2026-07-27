<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');

$cursor = $users->find([
    'id' => ['$exists' => false],
    '_id' => ['$type' => 'int'],
]);

$fixed = 0;
foreach ($cursor as $doc) {
    $oldId = (int) $doc['_id'];
    $new = $doc->getArrayCopy();
    unset($new['_id']);
    $new['_id'] = new MongoDB\BSON\ObjectId();
    $new['id'] = $oldId;

    $users->insertOne($new);
    $users->deleteOne(['_id' => $oldId]);
    $fixed++;
}

echo "fixed: {$fixed}\n";

