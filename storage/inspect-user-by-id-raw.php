<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');

$docInt = $users->findOne(['id' => 1]);
$docStr = $users->findOne(['id' => '1']);

echo "raw find id=1: ".($docInt ? 'FOUND' : 'NOT FOUND').PHP_EOL;
echo "raw find id='1': ".($docStr ? 'FOUND' : 'NOT FOUND').PHP_EOL;

if ($docInt) {
    echo "int doc email: ".$docInt['email'].PHP_EOL;
}
if ($docStr) {
    echo "str doc email: ".$docStr['email'].PHP_EOL;
}

