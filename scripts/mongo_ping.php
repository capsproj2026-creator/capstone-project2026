<?php

declare(strict_types=1);

use App\Support\MongoConnection;

// Keep startup checks from blocking for the full Atlas timeout (often 30s+).
foreach ([
    'MONGODB_CONNECT_TIMEOUT_MS' => '15000',
    'MONGODB_SERVER_SELECTION_TIMEOUT_MS' => '15000',
    'MONGODB_SOCKET_TIMEOUT_MS' => '15000',
] as $key => $ms) {
    putenv($key . '=' . $ms);
    $_ENV[$key] = $ms;
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    MongoConnection::ping();
    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . "\n" . $e->getMessage() . "\n");
    exit(1);
}

