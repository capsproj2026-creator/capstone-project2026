<?php

namespace Database\Seeders;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use Illuminate\Database\Seeder;

/**
 * Optional dev/demo AI parking lots (areas 19–21). Not included in CapstoneSeeder.
 * Run: php artisan db:seed --class=AiTestLotSeeder
 * Remove: php artisan ai-parking:remove-test-lots
 */
class AiTestLotSeeder extends Seeder
{
    public const AREA_ID = 19;

    public const CAPACITY = 20;

    public const SLOT_ID_START = 1068;

    /** @var list<array{id:int,name:string,prefix:string,slot_start:int,capacity:int,notes:string}> */
    public const LOTS = [
        [
            'id' => 19,
            'name' => 'AI Test Lot',
            'prefix' => 'AI',
            'slot_start' => 1068,
            'capacity' => 20,
            'notes' => 'YOLOv9 CAM-AI-1 (wired) Student/Staff',
        ],
        [
            'id' => 20,
            'name' => 'AI Lot B',
            'prefix' => 'AIB',
            'slot_start' => 1200,
            'capacity' => 20,
            'notes' => 'YOLOv9 CAM-AI-2 (Tapo) Student/Staff',
        ],
        [
            'id' => 21,
            'name' => 'AI Lot C',
            'prefix' => 'AIC',
            'slot_start' => 1300,
            'capacity' => 20,
            'notes' => 'YOLOv9 CAM-AI-3 (Tapo) Student/Staff',
        ],
    ];

    public function run(): void
    {
        foreach (self::LOTS as $lot) {
            ParkingArea::query()->updateOrCreate(
                ['id' => $lot['id']],
                [
                    'area_name' => $lot['name'],
                    'capacity' => $lot['capacity'],
                    'designation_notes' => $lot['notes'],
                    'is_visible' => true,
                    'allowed_roles' => ['Student', 'Staff'],
                ]
            );

            for ($i = 1; $i <= $lot['capacity']; $i++) {
                ParkingSlot::query()->updateOrCreate(
                    ['id' => $lot['slot_start'] + ($i - 1)],
                    [
                        'area_id' => $lot['id'],
                        'slot_number' => $lot['prefix'].'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'status' => 'Available',
                        'parked_user_id' => null,
                    ]
                );
            }
        }
    }
}
