<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Process;
use App\Services\CampusId\CampusIdPythonResolver;

$binary = CampusIdPythonResolver::binary();
echo "binary: {$binary}\n";

$result = Process::timeout(30)->run(array_merge(
    CampusIdPythonResolver::commandPrefix($binary),
    ['-c', 'import cv2, easyocr; print("imports ok")']
));

echo 'exit: '.$result->exitCode()."\n";
echo 'out: '.trim($result->output())."\n";
echo 'err: '.trim($result->errorOutput())."\n";
