<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Models\User::query()
    ->where('user_role_id', 2) // Guard
    ->where('status', 'Pending')
    ->update(['status' => 'Granted']);

echo "Granted pending Guard users.\n";

