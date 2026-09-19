<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Vehicle;
use App\Services\UserVehicleService;
use Tests\TestCase;

class UserVehicleProfileTest extends TestCase
{
    private ?User $student = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->student = User::query()
                ->where('user_role_id', 3)
                ->where('status', User::STATUS_GRANTED)
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->student) {
            $this->markTestSkipped('No granted student available.');
        }

        if (! $this->student->hasVerifiedEmail()) {
            $this->student->update(['email_verified_at' => now()]);
        }
    }

    public function test_account_settings_shows_multi_vehicle_section(): void
    {
        $this->actingAs($this->student->fresh())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Registered Vehicles', false)
            ->assertSee('up to '.UserVehicle::MAX_PER_USER.' vehicles', false)
            ->assertSee('Plate Number', false);
    }

    public function test_user_cannot_register_more_than_max_vehicles(): void
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $service = app(UserVehicleService::class);
        $user = $this->student->fresh();
        $createdIds = [];

        try {
            $existing = $service->listFor($user);
            if ($existing->count() > UserVehicle::MAX_PER_USER) {
                $this->markTestSkipped('Test student already has more than the max vehicles.');
            }

            $needed = UserVehicle::MAX_PER_USER - $existing->count();
            for ($i = 0; $i < $needed; $i++) {
                $row = $service->add($user, [
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => 'MX'.random_int(10000, 99999).$i,
                ]);
                $createdIds[] = $row->id;
            }

            $this->assertSame(UserVehicle::MAX_PER_USER, $service->listFor($user->fresh())->count());

            $this->actingAs($user->fresh())
                ->from(route('profile.edit'))
                ->post(route('profile.update'), [
                    'add_vehicle' => '1',
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => 'MX'.random_int(10000, 99999),
                ])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('plate_number');

            $html = $this->actingAs($user->fresh())
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('reached the maximum of '.UserVehicle::MAX_PER_USER, false)
                ->getContent();

            $this->assertStringNotContainsString('name="add_vehicle"', $html);
        } finally {
            if ($createdIds !== []) {
                UserVehicle::query()->whereIn('id', $createdIds)->delete();
                $service->syncPrimaryToUser($user->fresh());
            }
        }
    }

    public function test_user_can_add_multiple_vehicles_without_replacing(): void
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $service = app(UserVehicleService::class);
        $user = $this->student->fresh();
        $createdIds = [];

        try {
            $plateA = 'MV'.random_int(1000, 9999);
            $plateB = 'MV'.random_int(1000, 9999);
            while ($plateB === $plateA) {
                $plateB = 'MV'.random_int(1000, 9999);
            }

            $this->actingAs($user)
                ->post(route('profile.update'), [
                    'add_vehicle' => '1',
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $plateA,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->actingAs($user->fresh())
                ->post(route('profile.update'), [
                    'add_vehicle' => '1',
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $plateB,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $list = $service->listFor($user->fresh());
            $plates = $list->map(fn (UserVehicle $row) => strtoupper((string) $row->plate_number))->all();
            $createdIds = $list->pluck('id')->all();

            $this->assertContains(strtoupper($plateA), $plates);
            $this->assertContains(strtoupper($plateB), $plates);
            $this->assertGreaterThanOrEqual(2, count(array_intersect($plates, [strtoupper($plateA), strtoupper($plateB)])));
        } finally {
            UserVehicle::query()->whereIn('id', $createdIds)->delete();
            $service->syncPrimaryToUser($user->fresh());
        }
    }

    public function test_user_can_update_and_remove_vehicle(): void
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $service = app(UserVehicleService::class);
        $user = $this->student->fresh();
        $row = null;

        try {
            $plate = 'ED'.random_int(1000, 9999);
            $row = $service->add($user, [
                'vehicle_id' => $vehicle->id,
                'plate_number' => $plate,
            ]);

            $updatedPlate = 'ED'.random_int(1000, 9999);
            while ($updatedPlate === $plate) {
                $updatedPlate = 'ED'.random_int(1000, 9999);
            }

            $this->actingAs($user->fresh())
                ->post(route('profile.update'), [
                    'update_vehicle' => '1',
                    'user_vehicle_id' => $row->id,
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $updatedPlate,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $row->refresh();
            $this->assertSame(strtoupper($updatedPlate), strtoupper((string) $row->plate_number));

            $this->actingAs($user->fresh())
                ->post(route('profile.update'), [
                    'remove_vehicle' => '1',
                    'user_vehicle_id' => $row->id,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertNull(UserVehicle::query()->find($row->id));
            $row = null;
        } finally {
            if ($row) {
                UserVehicle::query()->where('id', $row->id)->delete();
                $service->syncPrimaryToUser($user->fresh());
            }
        }
    }

    public function test_user_cannot_save_duplicate_plate(): void
    {
        $other = User::query()
            ->where('id', '!=', $this->student->id)
            ->whereNotNull('plate_number')
            ->where('plate_number', '!=', '')
            ->first();

        if (! $other) {
            $this->markTestSkipped('No other user with a plate available.');
        }

        $vehicle = Vehicle::query()->orderBy('id')->first();

        $this->actingAs($this->student->fresh())
            ->from(route('profile.edit'))
            ->post(route('profile.update'), [
                'add_vehicle' => '1',
                'vehicle_id' => $vehicle->id,
                'plate_number' => $other->plate_number,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('plate_number');
    }

    public function test_user_cannot_add_same_plate_twice(): void
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $service = app(UserVehicleService::class);
        $user = $this->student->fresh();
        $row = null;

        try {
            $plate = 'DP'.random_int(1000, 9999);
            $row = $service->add($user, [
                'vehicle_id' => $vehicle->id,
                'plate_number' => $plate,
            ]);

            $this->actingAs($user->fresh())
                ->from(route('profile.edit'))
                ->post(route('profile.update'), [
                    'add_vehicle' => '1',
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $plate,
                ])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('plate_number');
        } finally {
            if ($row) {
                UserVehicle::query()->where('id', $row->id)->delete();
                $service->syncPrimaryToUser($user->fresh());
            }
        }
    }
}
