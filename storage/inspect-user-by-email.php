<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Usage: php storage/inspect-user-by-email.php <email>\n");
    exit(1);
}

$u = \App\Models\User::query()->where('email', $email)->first();
if (! $u) {
    echo "Not found: {$email}\n";
    exit(0);
}

var_export([
    'id' => $u->id,
    'email' => $u->email,
    'status' => $u->status,
    'user_role_id' => $u->user_role_id,
    'roleName' => $u->roleName(),
]);
echo PHP_EOL;

