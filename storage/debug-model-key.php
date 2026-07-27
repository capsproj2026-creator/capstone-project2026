<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$model = new \App\Models\User();

echo "User model keyName: ".$model->getKeyName().PHP_EOL;
echo "User model primaryKey prop: ".(new ReflectionClass($model))->getProperty('primaryKey')->getValue($model).PHP_EOL;
echo "User model keyType: ".($model->keyType ?? 'null').PHP_EOL;
echo "User model getKey(): ".($model->getKey() ? gettype($model->getKey()).':'.$model->getKey() : 'null').PHP_EOL;

// Explicit query tests
$q1 = \App\Models\User::query()->where('id', 1)->first();
$q2 = \App\Models\User::query()->where('_id', 1)->first();
echo "where(id,1) -> ".($q1 ? 'FOUND' : 'NOT FOUND').PHP_EOL;
echo "where(_id,1) -> ".($q2 ? 'FOUND' : 'NOT FOUND').PHP_EOL;

