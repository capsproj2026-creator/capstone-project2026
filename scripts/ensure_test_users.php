<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = [
    ['id' => 1, 'role_name' => 'Admin'],
    ['id' => 2, 'role_name' => 'Guard'],
    ['id' => 3, 'role_name' => 'Student'],
    ['id' => 4, 'role_name' => 'Staff'],
];

foreach ($roles as $role) {
    UserRole::query()->updateOrCreate(['id' => $role['id']], $role);
}

$defaults = [
    'phone_number' => '09000000000',
    'password' => Hash::make('password123'),
    'plate_number' => 'N/A',
    'profile_pic' => 'default_avatar.png',
    'driver_license' => 'N/A',
    'or_cr_photo' => 'N/A',
    'Gate_access' => 'Access',
    'strike_count' => 0,
    'email_verified_at' => now(),
    'created_at' => now(),
    'status' => 'Granted',
];

User::query()->updateOrCreate(
    ['email' => 'admin@my.cspc.edu.ph'],
    array_merge($defaults, [
        'id' => 1,
        'fullname' => 'System Administrator',
        'email' => 'admin@my.cspc.edu.ph',
        'password' => Hash::make('admin123'),
        'user_role_id' => 1,
        'id_number' => 'ADMIN-001',
    ])
);

User::query()->updateOrCreate(
    ['email' => 'guard@my.cspc.edu.ph'],
    array_merge($defaults, [
        'id' => 2,
        'fullname' => 'Test Guard',
        'email' => 'guard@my.cspc.edu.ph',
        'user_role_id' => 2,
        'id_number' => 'GUARD-001',
    ])
);

// Remove legacy test student/staff accounts if present.
User::query()->whereIn('email', [
    'student@cspc.edu',
    'staff@cspc.edu',
    'pending-student@cspc.edu',
    'pending-staff@cspc.edu',
])->delete();

$emails = [
    'admin' => 'admin@my.cspc.edu.ph',
    'guard' => 'guard@my.cspc.edu.ph',
];

$ids = [];
$missing = [];
foreach ($emails as $key => $email) {
    $u = User::query()->where('email', $email)->first();
    if (! $u) {
        $missing[] = $email;
        continue;
    }
    $ids[$key] = $u->id;
}

file_put_contents(__DIR__ . '/test_user_ids.json', json_encode($ids, JSON_PRETTY_PRINT));

fwrite(STDOUT, empty($missing) ? "OK\n" : ("MISSING_EMAILS=" . implode(',', $missing) . "\n"));
