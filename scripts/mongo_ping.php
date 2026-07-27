<?php

declare(strict_types=1);

use App\Support\MongoConnection;

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

