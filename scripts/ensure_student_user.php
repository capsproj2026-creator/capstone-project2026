<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

User::query()->updateOrCreate(
    ['email' => 'student@my.cspc.edu.ph'],
    [
        'fullname' => 'Test Student',
        'email' => 'student@my.cspc.edu.ph',
        'phone_number' => '09171234567',
        'password' => Hash::make('password123'),
        'user_role_id' => 3,
        'id_number' => 'STU-2026-001',
        'plate_number' => 'ABC-1234',
        'profile_pic' => 'default_avatar.png',
        'driver_license' => 'N/A',
        'or_cr_photo' => 'N/A',
        'status' => 'Granted',
        'Gate_access' => 'Access',
        'strike_count' => 0,
        'email_verified_at' => now(),
        'created_at' => now(),
    ]
);

fwrite(STDOUT, "OK student@my.cspc.edu.ph\n");
