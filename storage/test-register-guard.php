<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = $app->make(\App\Http\Controllers\Auth\RegisterController::class);

$uniq = bin2hex(random_bytes(4));

$data = [
    'fullname' => "Guard Test {$uniq}",
    'email' => "guard_{$uniq}@example.com",
    'phone_number' => '09000000'.substr($uniq, 0, 4),
    'password' => 'password123',
    'id_number' => "GUARD-{$uniq}",
    'reg_category' => 'personnel',
    'system_role' => 'Guard',
];

$request = \Illuminate\Http\Request::create('/register', 'POST', $data);

try {
    $response = $controller->store($request);
    echo "Registration succeeded.\n";
    echo "Redirect: ";
    var_export($response?->getTargetUrl() ?: $response?->getStatusCode());
    echo PHP_EOL;

    $user = \App\Models\User::query()->where('email', $data['email'])->first();
    if ($user) {
        echo "Created user id={$user->id} user_role_id={$user->user_role_id}\n";
    } else {
        echo "User not found after registration (unexpected).\n";
    }
} catch (\Throwable $e) {
    echo "Registration failed.\n";
    echo "Exception: ".get_class($e)."\n";
    echo "Message: ".$e->getMessage()."\n";
    echo "File: ".$e->getFile().":".$e->getLine()."\n";
    echo "Trace (top 5):\n";
    $trace = $e->getTrace();
    for ($i = 0; $i < min(5, count($trace)); $i++) {
        $t = $trace[$i];
        $file = $t['file'] ?? '[internal]';
        $line = $t['line'] ?? '';
        $func = $t['function'] ?? '';
        echo "- {$file}:{$line} {$func}\n";
    }
}

