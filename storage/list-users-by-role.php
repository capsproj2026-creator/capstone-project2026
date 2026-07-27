<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$roleId = (int) ($argv[1] ?? 0);
if ($roleId <= 0) {
    fwrite(STDERR, "Usage: php storage/list-users-by-role.php <user_role_id>\n");
    exit(1);
}

$users = \App\Models\User::query()
    ->where('user_role_id', $roleId)
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get(['id', 'email', 'status', 'user_role_id']);

foreach ($users as $u) {
    echo "id={$u->id} email={$u->email} status={$u->status}\n";
}

