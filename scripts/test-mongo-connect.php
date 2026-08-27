<?php

declare(strict_types=1);

$uri = $argv[1] ?? getenv('MONGODB_URI') ?: '';
if ($uri === '') {
    fwrite(STDERR, "Usage: php scripts/test-mongo-connect.php <mongodb-uri>\n");
    exit(1);
}

echo "Testing connection...\n";
$started = microtime(true);

try {
    $manager = new MongoDB\Driver\Manager($uri, [
        'serverSelectionTimeoutMS' => 15000,
        'connectTimeoutMS' => 15000,
    ]);
    $server = $manager->selectServer(new MongoDB\Driver\ReadPreference(MongoDB\Driver\ReadPreference::PRIMARY));
    $elapsed = round((microtime(true) - $started) * 1000);
    echo "OK in {$elapsed}ms via ".$server->getHost()."\n";
    exit(0);
} catch (Throwable $e) {
    $elapsed = round((microtime(true) - $started) * 1000);
    fwrite(STDERR, "FAILED in {$elapsed}ms: ".$e->getMessage()."\n");
    exit(1);
}
