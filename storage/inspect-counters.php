<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$counters = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('counters');
$users = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');

$counterDoc = $counters->findOne(['_id' => 'users']);

$maxDocs = $users->aggregate([
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

echo "counters.users.seq: ".(isset($counterDoc['seq']) ? (int) $counterDoc['seq'] : 'null').PHP_EOL;
echo "max users id: {$maxId}".PHP_EOL;

