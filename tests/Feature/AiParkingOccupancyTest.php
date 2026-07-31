<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiParkingOccupancyTest extends TestCase
{
    private const TOKEN = 'test-ai-parking-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ai_parking.api_token', self::TOKEN);
        Config::set('services.ai_parking.area_id', AiTestLotSeeder::AREA_ID);

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
        $other = ParkingArea::query()->where('id', '!=', AiTestLotSeeder::AREA_ID)->first();
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

    public function test_guard_ai_parking_stream_requires_auth(): void
    {
        $this->get(route('guard.ai-parking.stream'))->assertRedirect();

        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not seeded.');
        }

        $this->actingAs($guard)
            ->get(route('guard.ai-parking.stream'))
            ->assertStatus(503);
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

    public function test_ai_test_lot_area_exists(): void
    {
        $area = ParkingArea::query()->find(AiTestLotSeeder::AREA_ID);
        $this->assertNotNull($area);
        $this->assertSame('AI Test Lot', $area->area_name);
        $this->assertSame(20, (int) $area->capacity);
    }
}
