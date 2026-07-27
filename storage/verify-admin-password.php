<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'admin@cspc.edu';
$password = 'admin123';

$user = \App\Models\User::query()->where('email', $email)->first();
if (! $user) {
    echo "Admin user not found for {$email}\n";
    exit(1);
}

$hash = (string) $user->password;
$ok = password_verify($password, $hash);

echo "admin email: {$user->email}\n";
echo "admin id: {$user->id}\n";
echo "status: ".($user->status ?? 'null')."\n";
echo "password_verify(admin123): ".($ok ? 'true' : 'false')."\n";

