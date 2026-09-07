<?php

namespace App\Services;

use App\Models\RegisteredPlate;
use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Visitor;
use App\Support\PlateLookup;
use Illuminate\Support\Collection;

/**
 * Keeps registered_plates in sync so OCR / gate scans hit one table.
 */
class RegisteredPlateService
{
    public function findByPlate(?string $plate): ?RegisteredPlate
    {
        foreach (PlateLookup::searchKeys($plate) as $normalized) {
            if ($normalized === '') {
                continue;
            }

            $row = RegisteredPlate::query()
                ->where('plate_number', $normalized)
                ->where('status', RegisteredPlate::STATUS_ACTIVE)
                ->first();

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public function syncUserVehicle(UserVehicle $vehicle, ?User $user = null): RegisteredPlate
    {
        $user ??= User::query()->with(['role', 'department'])->find($vehicle->user_id);
        $vehicle->loadMissing('vehicleType');
        $plate = PlateLookup::normalize((string) $vehicle->plate_number);

        if ($plate === '' || ! $user) {
            throw new \InvalidArgumentException('Plate and owner are required.');
        }

        $payload = [
            'plate_number' => $plate,
            'plate_display' => strtoupper(trim((string) $vehicle->plate_number)) ?: $plate,
            'owner_type' => RegisteredPlate::OWNER_USER,
            'owner_id' => (int) $user->id,
            'user_vehicle_id' => (int) $vehicle->id,
            'visitor_id' => null,
            'vehicle_id' => $vehicle->vehicle_id ? (int) $vehicle->vehicle_id : null,
            'vehicle_type_name' => $vehicle->vehicleType?->vehicle_name,
            'vehicle_model' => $vehicle->vehicle_model,
            'vehicle_color' => $vehicle->vehicle_color,
            'owner_name' => $user->displayName(),
            'owner_role' => $user->displayRoleLabel(),
            'id_number' => $user->id_number,
            'department' => $user->department?->departmentname
                ?? (filled($user->department_code) ? (string) $user->department_code : null),
            'status' => RegisteredPlate::STATUS_ACTIVE,
            'is_primary' => (bool) $vehicle->is_primary,
        ];

        $existing = RegisteredPlate::query()
            ->where('user_vehicle_id', (int) $vehicle->id)
            ->first()
            ?? RegisteredPlate::query()
                ->where('plate_number', $plate)
                ->where('owner_type', RegisteredPlate::OWNER_USER)
                ->first();

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return RegisteredPlate::query()->create($payload);
    }

    public function removeUserVehicle(int $userVehicleId): void
    {
        RegisteredPlate::query()
            ->where('user_vehicle_id', $userVehicleId)
            ->delete();
    }

    public function syncVisitor(Visitor $visitor): ?RegisteredPlate
    {
        $plate = PlateLookup::normalize((string) ($visitor->plate_number ?? ''));
        if ($plate === '') {
            return null;
        }

        $visitor->loadMissing('vehicleType');

        $payload = [
            'plate_number' => $plate,
            'plate_display' => strtoupper(trim((string) $visitor->plate_number)) ?: $plate,
            'owner_type' => RegisteredPlate::OWNER_VISITOR,
            'owner_id' => (int) $visitor->id,
            'user_vehicle_id' => null,
            'visitor_id' => (int) $visitor->id,
            'vehicle_id' => $visitor->vehicle_id ? (int) $visitor->vehicle_id : null,
            'vehicle_type_name' => $visitor->vehicleType?->vehicle_name,
            'vehicle_model' => $visitor->vehicle_model ?? null,
            'vehicle_color' => $visitor->vehicle_color ?? null,
            'owner_name' => $visitor->displayName(),
            'owner_role' => 'Visitor',
            'id_number' => null,
            'department' => $visitor->office_to_visit,
            'status' => in_array($visitor->status, Visitor::ACTIVE_STATUSES, true)
                ? RegisteredPlate::STATUS_ACTIVE
                : (string) $visitor->status,
            'is_primary' => true,
        ];

        $existing = RegisteredPlate::query()
            ->where('visitor_id', (int) $visitor->id)
            ->first()
            ?? RegisteredPlate::query()
                ->where('plate_number', $plate)
                ->where('owner_type', RegisteredPlate::OWNER_VISITOR)
                ->first();

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return RegisteredPlate::query()->create($payload);
    }

    /**
     * Rebuild the scan table from user_vehicles + active visitors + legacy user plates.
     */
    public function rebuild(): int
    {
        RegisteredPlate::query()->delete();
        $count = 0;

        UserVehicle::query()->with(['vehicleType'])->orderBy('id')->chunk(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                try {
                    $this->syncUserVehicle($row);
                    $count++;
                } catch (\Throwable) {
                    // skip bad rows
                }
            }
        });

        // Legacy users with plate but no user_vehicles row yet.
        User::query()
            ->whereNotNull('plate_number')
            ->where('plate_number', '!=', '')
            ->whereNotIn('plate_number', ['N/A', 'n/a', 'NA', 'NONE', 'None'])
            ->orderBy('id')
            ->chunk(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $plate = PlateLookup::normalize((string) $user->plate_number);
                    if ($plate === '') {
                        continue;
                    }
                    if (RegisteredPlate::query()->where('plate_number', $plate)->exists()) {
                        continue;
                    }
                    $uv = app(UserVehicleService::class)->ensureMigrated($user)->first();
                    if ($uv) {
                        $this->syncUserVehicle($uv, $user);
                        $count++;
                    }
                }
            });

        Visitor::query()
            ->whereIn('status', Visitor::ACTIVE_STATUSES)
            ->whereNotNull('plate_number')
            ->where('plate_number', '!=', '')
            ->orderBy('id')
            ->chunk(100, function ($visitors) use (&$count) {
                foreach ($visitors as $visitor) {
                    if ($this->syncVisitor($visitor)) {
                        $count++;
                    }
                }
            });

        PlateLookup::forgetIndex();

        return $count;
    }

    public function allForMonitor(?string $search = null): Collection
    {
        $q = RegisteredPlate::query()
            ->where('status', RegisteredPlate::STATUS_ACTIVE)
            ->orderBy('plate_number');

        $rows = $q->get();

        if (filled($search)) {
            $needle = strtoupper(trim($search));
            $rows = $rows->filter(function (RegisteredPlate $row) use ($needle) {
                $hay = strtoupper(implode(' ', [
                    $row->plate_number,
                    $row->plate_display,
                    $row->owner_name,
                    $row->owner_role,
                    $row->id_number,
                    $row->vehicle_type_name,
                    $row->vehicle_model,
                    $row->vehicle_color,
                ]));

                return str_contains($hay, $needle);
            })->values();
        }

        return $rows;
    }
}
