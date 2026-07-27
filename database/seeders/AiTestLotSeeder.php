<?php

namespace Database\Seeders;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use Illuminate\Database\Seeder;

/**
 * Idempotent upsert for the YOLOv9 AI Test Lot (area id 19).
 * Run: php artisan db:seed --class=AiTestLotSeeder
 */
class AiTestLotSeeder extends Seeder
{
    public const AREA_ID = 19;

    public const CAPACITY = 20;

    public const SLOT_ID_START = 1068;

    public function run(): void
    {
        ParkingArea::query()->updateOrCreate(
            ['id' => self::AREA_ID],
            [
                'area_name' => 'AI Test Lot',
                'capacity' => self::CAPACITY,
                'designation_notes' => 'YOLOv9 camera test zone Student/Staff',
                'is_visible' => true,
                'allowed_roles' => ['Student', 'Staff'],
            ]
        );

        for ($i = 1; $i <= self::CAPACITY; $i++) {
            ParkingSlot::query()->updateOrCreate(
                ['id' => self::SLOT_ID_START + ($i - 1)],
                [
                    'area_id' => self::AREA_ID,
                    'slot_number' => 'AI-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'status' => 'Available',
                    'parked_user_id' => null,
                ]
            );
        }
    }
}
