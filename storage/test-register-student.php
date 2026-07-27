<?php

/**
 * Creates a new Student user via RegisterController flow.
 * After creation, the status will be "Pending" (admin approval required).
 * You can later change status to "Granted" for routing tests.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = $app->make(\App\Http\Controllers\Auth\RegisterController::class);

$uniq = bin2hex(random_bytes(3));

$data = [
    'fullname' => "Student Test {$uniq}",
    'email' => "student_{$uniq}@example.com",
    'phone_number' => '09000000'.substr($uniq, 0, 4),
    'password' => 'password123',
    'id_number' => "STU-{$uniq}",
    'reg_category' => 'vehicle',
    'user_type' => 'Student',
    'plate_number' => 'ABC-'.$uniq,
];

$request = \Illuminate\Http\Request::create('/register', 'POST', $data);

$controller->store($request);

$user = \App\Models\User::query()->where('email', $data['email'])->first();

var_export([
    'created' => (bool) $user,
    'id' => $user?->id,
    'email' => $user?->email,
    'user_role_id' => $user?->user_role_id,
    'status' => $user?->status,
]);
echo PHP_EOL;

