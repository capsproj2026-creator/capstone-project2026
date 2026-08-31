<?php

namespace App\Console\Commands;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RemoveAiTestLotsCommand extends Command
{
    protected $signature = 'ai-parking:remove-test-lots {--force : Skip confirmation}';

    protected $description = 'Remove AI test parking areas (19–21), their slots, and cached occupancy';

    public function handle(): int
    {
        $areaIds = array_column(AiTestLotSeeder::LOTS, 'id');

        if (! $this->option('force') && ! $this->confirm('Delete AI test lots (areas '.implode(', ', $areaIds).') and all their slots?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $slotCount = ParkingSlot::query()->whereIn('area_id', $areaIds)->count();
        ParkingSlot::query()->whereIn('area_id', $areaIds)->delete();
        ParkingArea::query()->whereIn('id', $areaIds)->delete();

        Cache::forget('ai_parking:last');
        foreach (['CAM-AI-1', 'CAM-AI-2', 'CAM-AI-3'] as $cameraId) {
            Cache::forget('ai_parking:last:'.strtoupper($cameraId));
        }

        $this->info("Removed {$slotCount} slot(s) and ".count($areaIds).' AI test parking area(s).');
        $this->line('Set AI_PARKING_AREA_ID / AI_CAMERA_*_AREA_ID in .env to a real campus lot, then recalibrate zones.json.');

        return self::SUCCESS;
    }
}
