<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = $app->make(\App\Http\Controllers\Auth\LoginController::class);

$uniq = bin2hex(random_bytes(3));
$email = $argv[1] ?? 'admin@cspc.edu';
$password = $argv[2] ?? 'admin123';

$request = \Illuminate\Http\Request::create('/login', 'POST', [
    'email' => $email,
    'password' => $password,
]);

try {
    $response = $controller->login($request);
    echo "Login succeeded.\n";
    echo "Redirect: ".(string) ($response?->getTargetUrl() ?? '')."\n";
} catch (\Throwable $e) {
    echo "Login threw exception.\n";
    echo get_class($e).": ".$e->getMessage()."\n";
    echo "File: ".$e->getFile().":".$e->getLine()."\n";
}

