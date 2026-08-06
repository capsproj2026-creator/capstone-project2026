<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AiCameraRegistry;
use App\Services\AiParkingOccupancyService;

$plate = strtoupper(trim($argv[1] ?? 'RGB123'));
$cameraId = app(AiCameraRegistry::class)->primaryCameraId();
$service = app(AiParkingOccupancyService::class);

$snap = $service->applyOccupancy(
    (int) env('AI_PARKING_AREA_ID', 19),
    1,
    $cameraId,
    [[
        'class' => 'car',
        'confidence' => 0.91,
        'plate' => $plate,
        'plate_status' => 'ok',
        'ocr_confidence' => 0.88,
        'track_id' => 1,
    ]]
);

$det = $snap['detections'][0] ?? [];
$scans = $service->plateScansFromAllCameras();

fwrite(STDOUT, "Plate: {$plate}\n");
fwrite(STDOUT, 'Registered: '.json_encode($det['registered'] ?? null)."\n");
fwrite(STDOUT, 'Owner: '.($det['owner_name'] ?? '—')."\n");
fwrite(STDOUT, 'Role: '.($det['owner_role'] ?? '—')."\n");
fwrite(STDOUT, 'ID: '.($det['owner_id_number'] ?? '—')."\n");
fwrite(STDOUT, 'Plate scans cached: '.count($scans)."\n");
