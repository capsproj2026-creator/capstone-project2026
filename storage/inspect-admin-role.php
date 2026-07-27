<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::query()->where('email', 'admin@cspc.edu')->first();
if (! $user) {
    echo "admin not found\n";
    exit(1);
}

$role = $user->role;

var_export([
    'user_id' => $user->id,
    'user_role_id' => $user->user_role_id,
    'computed_roleName' => $user->roleName(),
    'role_loaded' => $role ? true : false,
    'role_doc' => $role ? $role->toArray() : null,
]);
echo PHP_EOL;

