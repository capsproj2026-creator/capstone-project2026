<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Process;

$python = base_path('.venv-campus-id-ocr/Scripts/python.exe');
$script = base_path('scripts/scan_campus_id.py');
$image = 'C:/Users/LIZZIE/.cursor/projects/c-Users-LIZZIE-Downloads-capstone-project2026-main/assets/c__Users_LIZZIE_AppData_Roaming_Cursor_User_workspaceStorage_2cf18ca19f646b0d2843bf538df84904_images_WIN_20260822_13_47_15_Pro-6477d38f-6db9-4606-9ec3-c1fd99837de2.png';

echo "=== bare process ===\n";
$r1 = Process::timeout(120)->run([$python, $script, $image]);
echo "exit={$r1->exitCode()} err=".substr(trim($r1->errorOutput()), 0, 200)."\n";

echo "=== no systemroot ===\n";
$r3 = Process::timeout(120)->env(['PATH' => getenv('PATH') ?: '', 'TEMP' => sys_get_temp_dir()])->run([$python, $script, $image]);
echo "exit={$r3->exitCode()} err=".substr(trim($r3->errorOutput()), 0, 400)."\n";

echo "=== only system32 path, no systemroot ===\n";
$r4 = Process::timeout(120)->env(['PATH' => 'C:\\Windows\\System32'])->run([$python, $script, $image]);
echo "exit={$r4->exitCode()} err=".substr(trim($r4->errorOutput()), 0, 400)."\n";
