<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$max = App\Models\User::max('id');
$next = App\Services\SequenceService::next('users');
echo "max user id: {$max}\n";
echo "next id: {$next}\n";
