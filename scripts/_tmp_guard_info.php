<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$g = App\Models\User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
echo json_encode([
    'found' => (bool) $g,
    'id' => $g?->id,
    'status' => $g?->status,
    'verified' => (string) $g?->email_verified_at,
    'role_id' => $g?->user_role_id,
    'role' => $g?->roleName(),
    'canPortal' => $g?->canAccessPortal(),
], JSON_PRETTY_PRINT), PHP_EOL;
