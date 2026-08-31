<?php

namespace App\Console\Commands;

use App\Models\ParkingArea;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use Illuminate\Console\Command;

class AiParkingCheckCommand extends Command
{
    protected $signature = 'ai-parking:check {--probe-stream : HTTP probe each MJPEG upstream URL}';

    protected $description = 'Verify YOLOv9 multi-camera AI parking CCTV connection';

    public function handle(
        AiParkingHealthService $health,
        AiParkingOccupancyService $occupancy,
        AiCameraRegistry $registry
    ): int {
        $this->info('YOLOv9 AI Parking — multi-camera check');
        $this->newLine();

        $token = (string) config('services.ai_parking.api_token', '');
        if ($token === '') {
            $this->error('AI_PARKING_API_TOKEN is not set in .env');
        } else {
            $this->line('API token: configured ('.strlen($token).' chars)');
        }

        $okAny = false;
        foreach ($registry->cameras() as $camera) {
            $this->newLine();
            $this->line("=== {$camera['id']} — {$camera['name']} ===");
            $area = ParkingArea::query()->find($camera['area_id']);
            if (! $area) {
                $this->warn("Parking area {$camera['area_id']} missing — pick a valid area_id in .env (see parking areas in admin).");
            } else {
                $this->line("Area: [{$area->id}] {$area->area_name}");
            }

            $upstream = $camera['stream_url'] ?? null;
            $this->line('Stream URL: '.($upstream ?: '(none)'));

            if ($this->option('probe-stream') && $upstream) {
                $reachable = $health->isStreamReachable($upstream);
                $this->line('Stream reachable: '.($reachable ? 'yes' : 'no'));
            }

            $ingest = $health->isIngestActive($camera['id']);
            $snap = $occupancy->latestSnapshot($camera['id']);
            $this->line('Ingest active: '.($ingest ? 'yes' : 'no'));
            if (is_array($snap) && ! empty($snap['updated_at_label'])) {
                $this->line('Last update: '.$snap['updated_at_label'].' vehicles='.($snap['vehicle_count'] ?? 0));
            }

            if ($ingest || ($this->option('probe-stream') && $upstream && $health->isStreamReachable($upstream))) {
                $okAny = true;
            }
        }

        $this->newLine();
        if ($token !== '' && $okAny) {
            $this->info('At least one AI camera appears connected.');
            $this->line('Guard UI: /guard/live-cameras, /guard/ai-parking');

            return self::SUCCESS;
        }

        $this->warn('AI parking is not fully connected yet.');
        $this->line('1. Set AI_CAMERA_1/2/3_* in .env (IPs, RTSP paths, passwords)');
        $this->line('2. php artisan db:seed --class=AiTestLotSeeder');
        $this->line('3. php artisan serve --host=0.0.0.0 --port=8000');
        $this->line('4. powershell -ExecutionPolicy Bypass -File .\\scripts\\start-ai-parking.ps1');

        return self::FAILURE;
    }
}
