<?php

namespace App\Console\Commands;

use App\Models\ParkingArea;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Console\Command;

class AiParkingCheckCommand extends Command
{
    protected $signature = 'ai-parking:check {--probe-stream : HTTP probe the MJPEG upstream URL}';

    protected $description = 'Verify YOLOv9 AI parking CCTV connection (token, area, stream, ingest)';

    public function handle(AiParkingHealthService $health, AiParkingOccupancyService $occupancy): int
    {
        $this->info('YOLOv9 AI Parking — connection check');
        $this->newLine();

        $token = (string) config('services.ai_parking.api_token', '');
        if ($token === '') {
            $this->error('AI_PARKING_API_TOKEN is not set in .env');
        } else {
            $this->line('API token: configured ('.strlen($token).' chars)');
        }

        $areaId = $occupancy->monitoredAreaId();
        $area = ParkingArea::query()->find($areaId);
        if (! $area) {
            $this->warn("Parking area {$areaId} not found — run: php artisan db:seed --class=AiTestLotSeeder");
        } else {
            $this->line("Monitored area: [{$area->id}] {$area->area_name}");
        }

        $upstream = $health->upstreamStreamUrl();
        if ($upstream === null) {
            $this->error('AI_PARKING_STREAM_URL is not set');
        } else {
            $this->line("Upstream stream: {$upstream}");
        }

        if ($this->option('probe-stream') && $upstream !== null) {
            $reachable = $health->isStreamReachable($upstream);
            $this->line('Stream reachable: '.($reachable ? 'yes' : 'no'));
        } else {
            $this->comment('Tip: use --probe-stream to test the MJPEG URL');
        }

        $ingest = $health->isIngestActive();
        $snapshot = $occupancy->latestSnapshot();
        $this->line('Ingest active: '.($ingest ? 'yes' : 'no'));
        if (is_array($snapshot) && ! empty($snapshot['updated_at_label'])) {
            $this->line('Last AI update: '.$snapshot['updated_at_label']);
        } else {
            $this->comment('No occupancy data yet — start scripts/start-ai-parking.ps1');
        }

        $this->newLine();
        if ($token !== '' && $area && ($ingest || ($this->option('probe-stream') && $health->isStreamReachable($upstream)))) {
            $this->info('AI parking appears connected.');
            $this->line('Guard UI: /guard/live-cameras, /guard/ai-parking');

            return self::SUCCESS;
        }

        $this->warn('AI parking is not fully connected yet.');
        $this->line('1. .\\scripts\\setup-yolov9.ps1');
        $this->line('2. Set AI_PARKING_API_TOKEN and AI_CAMERA_IP in .env');
        $this->line('3. php artisan serve --host=0.0.0.0 --port=8000');
        $this->line('4. .\\scripts\\start-ai-parking.ps1');

        return self::FAILURE;
    }
}
