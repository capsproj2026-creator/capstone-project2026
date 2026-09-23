<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'dsn_source='.App\Support\MongoDsn::resolvedSource().PHP_EOL;
echo 'offline='.(App\Support\OfflineMode::enabled() ? 'yes' : 'no').PHP_EOL;
echo 'app_offline_mode='.App\Support\OfflineMode::mode().PHP_EOL;
echo 'mailer='.config('mail.default').PHP_EOL;

try {
    App\Support\MongoConnection::ping();
    echo "ping=ok\n";
} catch (Throwable $e) {
    echo 'ping=fail: '.$e->getMessage()."\n";
}
