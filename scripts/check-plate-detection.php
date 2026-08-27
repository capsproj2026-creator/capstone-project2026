<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Services\AiParkingOccupancyService::class);

foreach (['CAM-1', 'CAM-2'] as $cameraId) {
    $snap = $service->latestSnapshot($cameraId);
    echo "=== {$cameraId} ===\n";
    if (! is_array($snap)) {
        echo "  No snapshot\n\n";
        continue;
    }
    echo '  vehicles: '.($snap['vehicle_count'] ?? 0)."\n";
    echo '  updated: '.($snap['updated_at_label'] ?? 'never')."\n";
    $detections = $snap['detections'] ?? [];
    $withPlate = 0;
    foreach ($detections as $det) {
        if (! empty($det['plate'])) {
            $withPlate++;
        }
    }
    echo "  detections: ".count($detections)." (with plate: {$withPlate})\n";
    foreach (array_slice($detections, 0, 5) as $i => $det) {
        echo '  #'.($i + 1).' '.($det['class'] ?? '?')
            .' plate='.($det['plate'] ?? '-')
            .' status='.($det['plate_status'] ?? '?')
            .' ocr='.($det['ocr_confidence'] ?? '-')
            ."\n";
    }
    echo "\n";
}
