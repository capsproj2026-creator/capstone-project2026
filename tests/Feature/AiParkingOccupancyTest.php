<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\ViolationLog;
use App\Support\PlateLookup;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiParkingOccupancyTest extends TestCase
{
    private const TOKEN = 'test-ai-parking-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ai_parking.api_token', self::TOKEN);
        Config::set('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID);
        Config::set('services.ai_parking.stream_base', 'http://127.0.0.1:8090');
        Config::set('services.ai_parking.cameras', [
            [
                'id' => 'CAM-AI-1',
                'name' => 'AI Test Lot',
                'location' => 'Parking Lot A',
                'area_id' => 19,
                'stream_path' => '/stream.mjpg',
                'stream_url' => 'http://127.0.0.1:8090/stream.mjpg',
                'enabled' => true,
            ],
            [
                'id' => 'CAM-AI-2',
                'name' => 'AI Lot B',
                'location' => 'Parking Lot B',
                'area_id' => 20,
                'stream_path' => '/CAM-AI-2/stream.mjpg',
                'stream_url' => 'http://127.0.0.1:8090/CAM-AI-2/stream.mjpg',
                'enabled' => true,
            ],
            [
                'id' => 'CAM-AI-3',
                'name' => 'AI Lot C',
                'location' => 'Visitor Parking',
                'area_id' => 21,
                'stream_path' => '/CAM-AI-3/stream.mjpg',
                'stream_url' => 'http://127.0.0.1:8090/CAM-AI-3/stream.mjpg',
                'enabled' => true,
            ],
        ]);

        try {
            (new AiTestLotSeeder)->run();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    public function test_rejects_missing_ai_token(): void
    {
        $this->postJson('/api/ai-parking/occupancy', [
            'vehicle_count' => 2,
            'area_id' => AiTestLotSeeder::AREA_ID,
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'unauthorized');
    }

    public function test_occupancy_updates_ai_test_lot_slots_by_vehicle_count(): void
    {
        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'area_id' => AiTestLotSeeder::AREA_ID,
                'vehicle_count' => 3,
                'detections' => [
                    ['class' => 'car', 'confidence' => 0.9],
                    ['class' => 'motorcycle', 'confidence' => 0.8],
                    ['class' => 'car', 'confidence' => 0.7],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.vehicle_count', 3)
            ->assertJsonPath('data.occupied', 3);

        $slots = ParkingSlot::query()
            ->where('area_id', AiTestLotSeeder::AREA_ID)
            ->orderBy('slot_number')
            ->get();

        $this->assertCount(20, $slots);
        $this->assertSame(3, $slots->where('status', 'Occupied')->count());
        $this->assertSame(17, $slots->where('status', 'Available')->count());
    }

    public function test_occupancy_ignores_client_area_id_override(): void
    {
        $other = ParkingArea::query()->where('id', '!=', AiTestLotSeeder::AREA_ID)->whereNotIn('id', [20, 21])->first();
        if (! $other) {
            $this->markTestSkipped('No secondary parking area to compare.');
        }

        $beforeOther = ParkingSlot::query()
            ->where('area_id', $other->id)
            ->get(['id', 'status'])
            ->mapWithKeys(fn ($s) => [(string) $s->id => $s->status])
            ->all();

        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'area_id' => $other->id,
                'vehicle_count' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.area_id', AiTestLotSeeder::AREA_ID);

        foreach ($beforeOther as $id => $status) {
            $slot = ParkingSlot::query()->find((int) $id);
            $this->assertSame($status, $slot?->status, "Non-AI area slot {$id} should be unchanged");
        }
    }

    public function test_multi_camera_occupancy_is_independent(): void
    {
        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'vehicle_count' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('data.area_id', 19)
            ->assertJsonPath('data.occupied', 4);

        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-2',
                'vehicle_count' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.camera_id', 'CAM-AI-2')
            ->assertJsonPath('data.area_id', 20)
            ->assertJsonPath('data.occupied', 2);

        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-3',
                'vehicle_count' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.area_id', 21)
            ->assertJsonPath('data.occupied', 1);

        $this->assertSame(4, ParkingSlot::query()->where('area_id', 19)->where('status', 'Occupied')->count());
        $this->assertSame(2, ParkingSlot::query()->where('area_id', 20)->where('status', 'Occupied')->count());
        $this->assertSame(1, ParkingSlot::query()->where('area_id', 21)->where('status', 'Occupied')->count());

        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if ($guard) {
            $this->actingAs($guard)
                ->getJson(route('guard.parking.status'))
                ->assertOk()
                ->assertJsonPath('ai_cameras.CAM-AI-1.occupied', 4)
                ->assertJsonPath('ai_cameras.CAM-AI-2.occupied', 2)
                ->assertJsonPath('ai_cameras.CAM-AI-3.occupied', 1);
        }
    }

    public function test_live_cameras_lists_ai_cameras(): void
    {
        $admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not seeded.');
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.live-cameras'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('AI Test Lot', $html);
        $this->assertStringContainsString('AI Lot B', $html);
        $this->assertStringContainsString('AI Lot C', $html);
    }

    public function test_guard_ai_parking_stream_requires_auth(): void
    {
        $this->get(route('guard.ai-parking.stream'))->assertRedirect();

        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not seeded.');
        }

        $response = $this->actingAs($guard)
            ->get(route('guard.ai-parking.stream'));

        // 503 when upstream AI stream is down; 200 when the Python service is live.
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_status_payload_includes_ai_health(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not seeded.');
        }

        $this->actingAs($guard)
            ->getJson(route('guard.parking.status'))
            ->assertOk()
            ->assertJsonStructure([
                'ai_health' => [
                    'configured',
                    'stream_reachable',
                    'ingest_active',
                    'connected',
                ],
            ]);
    }

    public function test_guard_ai_parking_monitor_loads(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not seeded.');
        }

        $this->actingAs($guard)
            ->get(route('guard.ai-parking'))
            ->assertOk()
            ->assertSee('AI Parking Monitor')
            ->assertDontSee('Redirecting');
    }

    public function test_user_parking_page_has_no_slot_assignment_copy(): void
    {
        $user = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->first();

        if (! $user) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        $user->update(['email_verified_at' => now()]);

        $html = $this->actingAs($user)
            ->get(route('user.parking'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Your Parking', $html);
        $this->assertStringNotContainsString('No slot assigned', $html);
        $this->assertStringNotContainsString('Contact administration if you need a parking assignment', $html);
    }

    public function test_occupancy_enriches_detection_with_registered_owner(): void
    {
        PlateLookup::forgetIndex();

        $owner = null;
        try {
            $owner = User::query()->create([
                'fullname' => 'Plate Owner Test',
                'email' => 'plate.owner.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'PLATE'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'ZZZ-4321',
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not create test user: '.$e->getMessage());
        }

        try {
            $response = $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
                ->postJson('/api/ai-parking/occupancy', [
                    'camera_id' => 'CAM-AI-1',
                    'area_id' => AiTestLotSeeder::AREA_ID,
                    'vehicle_count' => 1,
                    'detections' => [
                        [
                            'class' => 'car',
                            'confidence' => 0.91,
                            'plate' => 'ZZZ4321',
                            'track_id' => 42,
                        ],
                    ],
                ])
                ->assertOk()
                ->assertJsonPath('status', 'ok');

            $det = $response->json('data.detections.0');
            $this->assertSame('ZZZ4321', $det['plate']);
            $this->assertTrue($det['registered']);
            $this->assertSame('Plate Owner Test', $det['owner_name']);
            $this->assertSame('Registered', $det['registration_status']);
            $this->assertSame('Plate Owner Test', $det['owner_label']);
            $this->assertEquals($owner->id, $det['user_id']);
            $this->assertNotEmpty($det['department'] ?? $owner->department_code);
        } finally {
            PlateLookup::forgetIndex();
            if ($owner) {
                $owner->delete();
            }
        }
    }

    public function test_guard_can_correct_ocr_plate_on_a_track(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
            ?? User::query()->where('user_role_id', 2)->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not found.');
        }
        if (! $guard->hasVerifiedEmail()) {
            $guard->update(['email_verified_at' => now()]);
        }

        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'area_id' => AiTestLotSeeder::AREA_ID,
                'vehicle_count' => 1,
                'detections' => [
                    [
                        'class' => 'motorcycle',
                        'confidence' => 0.8,
                        'plate' => '05010406323',
                        'track_id' => 77,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($guard)
            ->postJson(route('guard.ai-parking.correct-plate'), [
                'camera_id' => 'CAM-AI-1',
                'track_id' => 77,
                'plate' => '0501-0401328',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.plate', '05010401328');
    }

    public function test_plate_lookup_endpoint_registered_and_unknown(): void
    {
        PlateLookup::forgetIndex();

        $owner = null;
        try {
            $owner = User::query()->create([
                'fullname' => 'Lookup Owner',
                'email' => 'lookup.owner.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'LOOK'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'XYZ-5678',
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not create test user: '.$e->getMessage());
        }

        try {
            $reg = $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
                ->postJson('/api/ai-parking/plate-lookup', ['plate' => 'XYZ5678'])
                ->assertOk()
                ->assertJsonPath('data.registered', true)
                ->assertJsonPath('data.owner_name', 'Lookup Owner')
                ->assertJsonPath('data.owner_role', $owner->roleName());

            $this->assertNotEmpty($reg->json('data.department'));

            $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
                ->postJson('/api/ai-parking/plate-lookup', ['plate' => 'NOPE0001'])
                ->assertOk()
                ->assertJsonPath('data.registered', false)
                ->assertJsonPath('data.owner_label', 'Unknown Vehicle')
                ->assertJsonPath('data.registration_status', 'Plate Not Registered');
        } finally {
            PlateLookup::forgetIndex();
            if ($owner) {
                $owner->delete();
            }
        }
    }

    public function test_occupancy_marks_unknown_vehicle_when_plate_not_registered(): void
    {
        PlateLookup::forgetIndex();

        $response = $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'area_id' => AiTestLotSeeder::AREA_ID,
                'vehicle_count' => 1,
                'detections' => [
                    [
                        'class' => 'car',
                        'confidence' => 0.88,
                        'plate' => 'ZZZ9999',
                        'plate_status' => 'ok',
                        'track_id' => 7,
                    ],
                ],
            ])
            ->assertOk();

        $det = $response->json('data.detections.0');
        $this->assertSame('ZZZ9999', $det['plate']);
        $this->assertFalse($det['registered']);
        $this->assertSame('Unknown Vehicle', $det['owner_label']);
        $this->assertSame('Plate Not Registered', $det['registration_status']);
        $this->assertNull($det['owner_name']);
    }

    public function test_occupancy_marks_unreadable_plate_without_inventing_text(): void
    {
        $response = $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'area_id' => AiTestLotSeeder::AREA_ID,
                'vehicle_count' => 1,
                'detections' => [
                    [
                        'class' => 'car',
                        'confidence' => 0.9,
                        'plate_status' => 'unreadable',
                        'ocr_confidence' => 0.2,
                        'track_id' => 9,
                    ],
                ],
            ])
            ->assertOk();

        $det = $response->json('data.detections.0');
        $this->assertSame('unreadable', $det['plate_status']);
        $this->assertSame('Plate Unreadable', $det['plate_label']);
        $this->assertNull($det['plate']);
        $this->assertNull($det['owner_name']);
    }

    public function test_occupancy_accepts_xyxy_without_breaking_legacy_payload(): void
    {
        $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
            ->postJson('/api/ai-parking/occupancy', [
                'camera_id' => 'CAM-AI-1',
                'vehicle_count' => 1,
                'detections' => [
                    [
                        'class' => 'car',
                        'confidence' => 0.9,
                        'track_id' => 3,
                        'xyxy' => [0.1, 0.2, 0.4, 0.5],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.vehicle_count', 1);
    }

    public function test_violation_event_stores_camera_area_and_evidence(): void
    {
        PlateLookup::forgetIndex();
        Mail::fake();
        Storage::fake('private');

        $owner = null;
        try {
            $owner = User::query()->create([
                'fullname' => 'Cite Owner',
                'email' => 'cite.owner.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'CITE'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'CIT-1111',
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not create test user: '.$e->getMessage());
        }

        // Minimal valid JPEG (1x1)
        $jpeg = base64_encode(
            hex2bin('ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffdb0043010909090c0b0c180d0d1832211c213232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232ffc00011080001000103011100021100031101ffc40014000100000000000000000000000000000000ffc40014100100000000000000000000000000000000ffda000c0301000210031000003f00bf80ffd9')
        );

        try {
            $response = $this->withHeaders(['X-AI-TOKEN' => self::TOKEN])
                ->postJson('/api/ai-parking/occupancy', [
                    'camera_id' => 'CAM-AI-1',
                    'vehicle_count' => 1,
                    'detections' => [
                        [
                            'class' => 'car',
                            'confidence' => 0.9,
                            'plate' => 'CIT1111',
                            'track_id' => 55,
                        ],
                    ],
                    'events' => [
                        [
                            'type' => 'no_parking',
                            'zone_id' => 'NP1',
                            'track_id' => 55,
                            'plate' => 'CIT1111',
                            'confidence' => 0.9,
                            'vehicle_details' => 'Automobiles',
                            'evidence_jpeg_base64' => $jpeg,
                        ],
                    ],
                ])
                ->assertOk();

            $det = $response->json('data.detections.0');
            $this->assertSame('no_parking', $det['violation_status'] ?? null);

            $log = ViolationLog::query()
                ->where('plate_number', 'CIT1111')
                ->orderByDesc('created_at')
                ->first();

            $this->assertNotNull($log);
            $this->assertSame('CAM-AI-1', $log->camera_id);
            $this->assertSame(AiTestLotSeeder::AREA_ID, (int) $log->area_id);
            $this->assertSame('Automobiles', $log->vehicle_details);
            $this->assertSame(55, (int) $log->track_id);
            $this->assertNotEmpty($log->evidence_photo);
            Storage::disk('private')->assertExists($log->evidence_photo);
            Storage::disk('public')->assertMissing($log->evidence_photo);
        } finally {
            PlateLookup::forgetIndex();
            if ($owner) {
                ViolationLog::query()->where('user_id', $owner->id)->delete();
                $owner->delete();
            }
        }
    }

    public function test_ai_test_lot_area_exists(): void
    {
        $area = ParkingArea::query()->find(AiTestLotSeeder::AREA_ID);
        $this->assertNotNull($area);
        $this->assertSame('AI Test Lot', $area->area_name);
        $this->assertSame(20, (int) $area->capacity);
    }
}
