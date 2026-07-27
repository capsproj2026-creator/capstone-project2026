<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$coll = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');
$doc = $coll->findOne([]);
var_export($doc);
echo PHP_EOL;

