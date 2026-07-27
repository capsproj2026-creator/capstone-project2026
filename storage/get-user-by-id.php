<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Usage: php storage/get-user-by-id.php <id>\n");
    exit(1);
}

$user = \App\Models\User::query()->where('id', $id)->first();

if (! $user) {
    echo "User id={$id} not found.\n";
    exit(0);
}

var_export([
    'id' => $user->id,
    'email' => $user->email,
    'fullname' => $user->fullname,
    'user_role_id' => $user->user_role_id,
    'status' => $user->status ?? null,
]);
echo PHP_EOL;

