<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * End-to-end checkup: CAM-1 / CAM-2 occupancy updates Mongo slots and
 * appears on admin, guard, and (role-filtered) user parking status APIs.
 */
class AiParkingRolePropagationTest extends TestCase
{
    private const TOKEN = 'test-ai-parking-propagation-token';

    private const AREA_ACAD = 910;

    private const AREA_DURAN = 911;

    /** @var list<int> */
    private array $slotIds = [];

    private ?User $admin = null;

    private ?User $guard = null;

    private ?User $staff = null;

    private ?User $student = null;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ai_parking.api_token', self::TOKEN);
        Config::set('services.ai_parking.area_id', self::AREA_ACAD);
        Config::set('services.ai_parking.cameras', [
            [
                'id' => 'CAM-1',
                'name' => 'ACAD 1 Building (Front)',
                'location' => 'ACAD 1 Building (Front)',
                'area_id' => self::AREA_ACAD,
                'stream_path' => '/stream.mjpg',
                'stream_url' => 'http://127.0.0.1:8090/stream.mjpg',
                'enabled' => true,
            ],
            [
                'id' => 'CAM-2',
                'name' => 'Duran Hall (Front)',
                'location' => 'Duran Hall (Front)',
                'area_id' => self::AREA_DURAN,
                'stream_path' => '/CAM-2/stream.mjpg',
                'stream_url' => 'http://127.0.0.1:8090/CAM-2/stream.mjpg',
                'enabled' => true,
            ],
        ]);

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
            $this->guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
            $this->staff = User::query()->where('user_role_id', 4)->orderBy('id')->first()
                ?? User::query()->where('email', 'like', '%staff%')->first();
            $this->student = User::query()->where('user_role_id', 3)->orderBy('id')->first()
                ?? User::query()->where('email', 'like', '%student%')->first();

            $this->seedLots();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin || ! $this->guard) {
            $this->markTestSkipped('Seeded admin/guard users required.');
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->slotIds !== []) {
                ParkingSlot::query()->whereIn('id', $this->slotIds)->delete();
            }
            ParkingArea::query()->whereIn('id', [self::AREA_ACAD, self::AREA_DURAN])->delete();
        } catch (\Throwable) {
            // ignore cleanup failures
        }

        parent::tearDown();
    }

    private function seedLots(): void
    {
        ParkingArea::query()->updateOrCreate(
            ['id' => self::AREA_ACAD],
            [
                'area_name' => 'Propagation ACAD Fixture',
                'capacity' => 4,
                'designation_notes' => 'College Officials',
                'is_visible' => true,
                'allowed_roles' => ['Staff'],
            ]
        );
        ParkingArea::query()->updateOrCreate(
            ['id' => self::AREA_DURAN],
            [
                'area_name' => 'Propagation Duran Fixture',
                'capacity' => 4,
                'designation_notes' => 'College Officials',
                'is_visible' => true,
                'allowed_roles' => ['Staff'],
            ]
        );

        $defs = [
            [self::AREA_ACAD, 91001, 'AC-1'],
            [self::AREA_ACAD, 91002, 'AC-2'],
            [self::AREA_DURAN, 91101, 'DU-1'],
            [self::AREA_DURAN, 91102, 'DU-2'],
        ];

        foreach ($defs as [$areaId, $slotId, $number]) {
            ParkingSlot::query()->updateOrCreate(
                ['id' => $slotId],
                [
                    'area_id' => $areaId,
                    'slot_number' => $number,
                    'status' => 'Available',
                    'parked_user_id' => null,
                    'parked_visitor_id' => null,
                ]
            );
            $this->slotIds[] = $slotId;
        }
    }

    public function test_cam1_and_cam2_occupancy_updates_admin_guard_and_staff_parking_status(): void
    {
        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-1',
                'area_id' => 999,
                'vehicle_count' => 1,
                'mode' => 'slots',
                'slots' => [
                    ['slot_number' => 'AC-1', 'occupied' => true],
                    ['slot_number' => 'AC-2', 'occupied' => false],
                ],
                'detections' => [['class' => 'car', 'confidence' => 0.9]],
            ])
            ->assertOk()
            ->assertJsonPath('data.area_id', self::AREA_ACAD)
            ->assertJsonPath('data.occupied', 1);

        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-2',
                'area_id' => 999,
                'vehicle_count' => 1,
                'mode' => 'slots',
                'slots' => [
                    ['slot_number' => 'DU-1', 'occupied' => true],
                    ['slot_number' => 'DU-2', 'occupied' => false],
                ],
                'detections' => [['class' => 'car', 'confidence' => 0.9]],
            ])
            ->assertOk()
            ->assertJsonPath('data.area_id', self::AREA_DURAN)
            ->assertJsonPath('data.occupied', 1);

        $this->assertSame(
            'Occupied',
            ParkingSlot::query()->where('area_id', self::AREA_ACAD)->where('slot_number', 'AC-1')->value('status')
        );
        $this->assertSame(
            'Available',
            ParkingSlot::query()->where('area_id', self::AREA_ACAD)->where('slot_number', 'AC-2')->value('status')
        );
        $this->assertSame(
            'Occupied',
            ParkingSlot::query()->where('area_id', self::AREA_DURAN)->where('slot_number', 'DU-1')->value('status')
        );
        $this->assertSame(
            'Available',
            ParkingSlot::query()->where('area_id', self::AREA_DURAN)->where('slot_number', 'DU-2')->value('status')
        );

        $adminStatus = $this->actingAs($this->admin->fresh())
            ->getJson(route('admin.parking.status'))
            ->assertOk()
            ->json();

        $guardStatus = $this->actingAs($this->guard->fresh())
            ->getJson(route('guard.parking.status'))
            ->assertOk()
            ->json();

        foreach ([$adminStatus, $guardStatus] as $payload) {
            $zones = collect($payload['zones'] ?? []);
            $acad = $zones->firstWhere('id', self::AREA_ACAD);
            $duran = $zones->firstWhere('id', self::AREA_DURAN);
            $this->assertNotNull($acad);
            $this->assertNotNull($duran);
            $this->assertSame(1, (int) ($acad['occupied'] ?? 0));
            $this->assertSame(1, (int) ($duran['occupied'] ?? 0));
            $this->assertTrue((bool) ($acad['ai_monitored'] ?? false));
            $this->assertTrue((bool) ($duran['ai_monitored'] ?? false));
        }

        if ($this->staff) {
            $staffZones = collect(
                $this->actingAs($this->staff->fresh())
                    ->getJson(route('user.parking.status'))
                    ->assertOk()
                    ->json('zones')
            );
            $this->assertNotNull($staffZones->firstWhere('id', self::AREA_ACAD));
            $this->assertNotNull($staffZones->firstWhere('id', self::AREA_DURAN));
            $this->assertSame(1, (int) data_get($staffZones->firstWhere('id', self::AREA_ACAD), 'occupied'));
            $this->assertSame(1, (int) data_get($staffZones->firstWhere('id', self::AREA_DURAN), 'occupied'));
        }

        if ($this->student) {
            $studentZones = collect(
                $this->actingAs($this->student->fresh())
                    ->getJson(route('user.parking.status'))
                    ->assertOk()
                    ->json('zones')
            );
            // Officials-only lots are Staff-scoped — students should not see them.
            $this->assertNull($studentZones->firstWhere('id', self::AREA_ACAD));
            $this->assertNull($studentZones->firstWhere('id', self::AREA_DURAN));
        }
    }
}
