<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sample = 'C:/Users/LIZZIE/.cursor/projects/c-Users-LIZZIE-Downloads-capstone-project2026-main/assets/c__Users_LIZZIE_AppData_Roaming_Cursor_User_workspaceStorage_2cf18ca19f646b0d2843bf538df84904_images_WIN_20260822_13_47_15_Pro-6477d38f-6db9-4606-9ec3-c1fd99837de2.png';

if (! is_file($sample)) {
    fwrite(STDERR, "Sample image missing\n");
    exit(1);
}

$file = Illuminate\Http\UploadedFile::fake()->createWithContent('id.jpg', file_get_contents($sample));
$result = app(App\Services\CampusId\CampusIdOcrService::class)->scan($file);

echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit(($result['ok'] ?? false) ? 0 : 1);
