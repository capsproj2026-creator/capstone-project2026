<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\PlateLookup;

PlateLookup::forgetIndex();

$tests = [
    ['ABC1234', true, 'Juan Dela Cruz'],
    ['XYZ-5678', true, 'Maria Santos'],
    ['NOPE9999', false, 'Unknown Vehicle'],
];

$ok = true;
foreach ($tests as [$plate, $expectRegistered, $expectLabel]) {
    $result = PlateLookup::identity($plate);
    $registered = (bool) ($result['registered'] ?? false);
    $label = $registered ? ($result['owner_name'] ?? '') : ($result['owner_label'] ?? '');

    if ($registered !== $expectRegistered || $label !== $expectLabel) {
        $ok = false;
        fwrite(STDERR, "FAIL {$plate}: registered=" . json_encode($registered) . " label={$label}\n");
    } else {
        fwrite(STDOUT, "OK {$plate} -> {$label}\n");
    }
}

exit($ok ? 0 : 1);
