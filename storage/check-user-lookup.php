<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 1);

$u1 = App\Models\User::query()->where('id', $id)->first();
$u2 = App\Models\User::query()->find($id);

echo "where(id={$id})->first: ".($u1 ? 'FOUND' : 'NOT FOUND').PHP_EOL;
echo "find({$id}): ".($u2 ? 'FOUND' : 'NOT FOUND').PHP_EOL;

if ($u1) {
    echo "u1 email: ".$u1->email.PHP_EOL;
}

