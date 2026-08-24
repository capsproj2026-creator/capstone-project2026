<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Vehicle;
use App\Support\PlateLookup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class UserVehicleService
{
    /**
     * Ensure legacy User.plate_number / vehicle_id appear in user_vehicles.
     */
    public function ensureMigrated(User $user): Collection
    {
        $vehicles = UserVehicle::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        if ($vehicles->isNotEmpty()) {
            return $vehicles->load('vehicleType');
        }

        $plate = PlateLookup::normalize((string) ($user->plate_number ?? ''));
        $vehicleId = (int) ($user->vehicle_id ?? 0);

        if ($plate === '' || $vehicleId <= 0) {
            return collect();
        }

        $row = UserVehicle::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleId,
            'plate_number' => $plate,
            'is_primary' => true,
        ]);

        return collect([$row->load('vehicleType')]);
    }

    public function listFor(User $user): Collection
    {
        return $this->ensureMigrated($user);
    }

    /**
     * @param  array{vehicle_id: int|string, plate_number: string}  $data
     */
    public function add(User $user, array $data): UserVehicle
    {
        $validated = $this->validatedPayload($data, $user);
        $this->assertPlateAvailable($validated['plate_number'], $user->id);

        $existing = $this->listFor($user);
        $isPrimary = $existing->isEmpty();

        $vehicle = UserVehicle::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $validated['vehicle_id'],
            'plate_number' => $validated['plate_number'],
            'is_primary' => $isPrimary,
        ]);

        $this->syncPrimaryToUser($user);
        PlateLookup::forgetIndex();

        return $vehicle->load('vehicleType');
    }

    /**
     * @param  array{vehicle_id: int|string, plate_number: string}  $data
     */
    public function update(User $user, UserVehicle $vehicle, array $data): UserVehicle
    {
        $this->assertOwned($user, $vehicle);
        $validated = $this->validatedPayload($data, $user);
        $this->assertPlateAvailable($validated['plate_number'], $user->id, (int) $vehicle->id);

        $vehicle->update([
            'vehicle_id' => $validated['vehicle_id'],
            'plate_number' => $validated['plate_number'],
        ]);

        $this->syncPrimaryToUser($user);
        PlateLookup::forgetIndex();

        return $vehicle->fresh()->load('vehicleType');
    }

    public function remove(User $user, UserVehicle $vehicle): void
    {
        $this->assertOwned($user, $vehicle);
        $wasPrimary = (bool) $vehicle->is_primary;
        $vehicle->delete();

        if ($wasPrimary) {
            $next = UserVehicle::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->first();
            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        $this->syncPrimaryToUser($user);
        PlateLookup::forgetIndex();
    }

    public function makePrimary(User $user, UserVehicle $vehicle): void
    {
        $this->assertOwned($user, $vehicle);

        UserVehicle::query()
            ->where('user_id', $user->id)
            ->update(['is_primary' => false]);

        $vehicle->update(['is_primary' => true]);
        $this->syncPrimaryToUser($user);
        PlateLookup::forgetIndex();
    }

    public function syncPrimaryToUser(User $user): void
    {
        $primary = UserVehicle::query()
            ->where('user_id', $user->id)
            ->where('is_primary', true)
            ->first();

        if (! $primary) {
            $primary = UserVehicle::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->first();
            if ($primary) {
                $primary->update(['is_primary' => true]);
            }
        }

        if ($primary) {
            $user->update([
                'plate_number' => $primary->plate_number,
                'vehicle_id' => $primary->vehicle_id,
            ]);

            return;
        }

        $user->update([
            'plate_number' => null,
            'vehicle_id' => null,
        ]);
    }

    /**
     * @param  array{vehicle_id: int|string, plate_number: string}  $data
     * @return array{vehicle_id: int, plate_number: string}
     */
    private function validatedPayload(array $data, User $user): array
    {
        $vehicleIds = Vehicle::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validator = validator($data, [
            'vehicle_id' => ['required', 'integer', Rule::in($vehicleIds)],
            'plate_number' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[A-Za-z0-9\-\s]+$/'],
        ], [
            'vehicle_id.required' => 'Please select a vehicle type.',
            'vehicle_id.in' => 'Please select a valid vehicle type.',
            'plate_number.required' => 'Please enter your plate number.',
            'plate_number.min' => 'Please enter a valid plate number.',
            'plate_number.regex' => 'Plate number may only contain letters, numbers, spaces, and hyphens.',
        ]);

        $validated = $validator->validate();
        $plate = PlateLookup::normalize($validated['plate_number']);

        if ($plate === '') {
            throw ValidationException::withMessages([
                'plate_number' => 'Please enter a valid plate number.',
            ]);
        }

        return [
            'vehicle_id' => (int) $validated['vehicle_id'],
            'plate_number' => $plate,
        ];
    }

    private function assertOwned(User $user, UserVehicle $vehicle): void
    {
        if ((int) $vehicle->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function assertPlateAvailable(string $plate, int $userId, ?int $ignoreVehicleId = null): void
    {
        $ownedDuplicate = UserVehicle::query()
            ->where('user_id', $userId)
            ->when($ignoreVehicleId, fn ($q) => $q->where('id', '<>', $ignoreVehicleId))
            ->get()
            ->first(fn (UserVehicle $row) => PlateLookup::normalize((string) $row->plate_number) === $plate);

        if ($ownedDuplicate) {
            throw ValidationException::withMessages([
                'plate_number' => 'You already registered this plate number.',
            ]);
        }

        $otherUserVehicle = UserVehicle::query()
            ->where('user_id', '<>', $userId)
            ->get()
            ->first(fn (UserVehicle $row) => PlateLookup::normalize((string) $row->plate_number) === $plate);

        if ($otherUserVehicle) {
            throw ValidationException::withMessages([
                'plate_number' => 'This plate number is already registered to another account.',
            ]);
        }

        $otherUser = User::query()
            ->where('id', '<>', $userId)
            ->whereNotNull('plate_number')
            ->where('plate_number', '!=', '')
            ->get()
            ->first(fn (User $other) => PlateLookup::normalize((string) $other->plate_number) === $plate);

        if ($otherUser) {
            throw ValidationException::withMessages([
                'plate_number' => 'This plate number is already registered to another account.',
            ]);
        }
    }
}
