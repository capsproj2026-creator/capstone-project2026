<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
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

    public function test_account_settings_shows_vehicle_section(): void
    {
        $this->actingAs($this->student->fresh())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Registered Vehicle', false)
            ->assertSee('Plate Number', false);
    }

    public function test_user_can_save_vehicle(): void
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $plate = 'UV'.random_int(1000, 9999);
        $originalPlate = $this->student->plate_number;
        $originalVehicleId = $this->student->vehicle_id;

        $this->actingAs($this->student->fresh())
            ->post(route('profile.update'), [
                'update_vehicle' => '1',
                'vehicle_id' => $vehicle->id,
                'plate_number' => $plate,
            ])
            ->assertRedirect();

        $this->student->refresh();
        $this->assertSame(strtoupper($plate), $this->student->plate_number);
        $this->assertSame((int) $vehicle->id, (int) $this->student->vehicle_id);

        $this->student->update([
            'plate_number' => $originalPlate,
            'vehicle_id' => $originalVehicleId,
        ]);
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
                'update_vehicle' => '1',
                'vehicle_id' => $vehicle->id,
                'plate_number' => $other->plate_number,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('plate_number');
    }
}
