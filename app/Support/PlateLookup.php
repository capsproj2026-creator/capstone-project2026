<?php

namespace App\Support;

use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;

/**
 * Normalize OCR / registration plate strings and resolve registered owners.
 */
class PlateLookup
{
    public static function normalize(?string $plate): string
    {
        $plate = strtoupper(trim((string) $plate));
        $plate = preg_replace('/[^A-Z0-9]/', '', $plate) ?? '';

        return $plate;
    }

    /**
     * Candidate spellings used for exact DB lookups (OCR often drops hyphens/spaces).
     *
     * @return list<string>
     */
    public static function candidates(?string $plate): array
    {
        $raw = strtoupper(trim((string) $plate));
        $normalized = self::normalize($plate);
        if ($normalized === '') {
            return [];
        }

        $out = [$normalized];
        if ($raw !== '' && $raw !== $normalized) {
            $out[] = $raw;
        }

        // Common PH private formats: ABC-1234 / ABC 1234
        if (preg_match('/^([A-Z]{2,3})(\d{3,4})$/', $normalized, $m)) {
            $out[] = $m[1].'-'.$m[2];
            $out[] = $m[1].' '.$m[2];
        }

        // LTO motorcycle plates: 0501-0401328 / 05010401328
        if (preg_match('/^(\d{4})(\d{7})$/', $normalized, $m)) {
            $out[] = $m[1].'-'.$m[2];
            $out[] = $m[1].' '.$m[2];
        }

        return array_values(array_unique($out));
    }

    public static function findUser(?string $plate): ?User
    {
        $candidates = self::candidates($plate);
        if ($candidates === []) {
            return null;
        }

        $user = User::query()
            ->with(['role', 'vehicleType', 'department'])
            ->whereIn('plate_number', $candidates)
            ->first();

        if ($user) {
            return $user;
        }

        $normalized = self::normalize($plate);
        $index = self::normalizedIndex();

        $userId = $index[$normalized] ?? null;
        if ($userId === null) {
            return null;
        }

        return User::query()->with(['role', 'vehicleType', 'department'])->where('id', $userId)->first();
    }

    public static function findVisitor(?string $plate): ?Visitor
    {
        $normalized = self::normalize($plate);
        if ($normalized === '') {
            return null;
        }

        $candidates = self::candidates($plate);

        $visitor = Visitor::query()
            ->with('vehicleType')
            ->whereIn('status', Visitor::ACTIVE_STATUSES)
            ->whereIn('plate_number', $candidates)
            ->orderByDesc('id')
            ->first();

        if ($visitor) {
            return $visitor;
        }

        return Visitor::query()
            ->with('vehicleType')
            ->whereIn('status', Visitor::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->get()
            ->first(fn (Visitor $v) => self::normalize((string) $v->plate_number) === $normalized);
    }

    /**
     * @return array{
     *     plate: string,
     *     user_id: int|string|null,
     *     visitor_id: int|string|null,
     *     owner_name: string|null,
     *     owner_label: string|null,
     *     id_number: string|null,
     *     role: string|null,
     *     purpose: string|null,
     *     registered: bool,
     *     vehicle_details: string|null,
     *     department: string|null,
     *     registration_status: string|null,
     *     is_visitor: bool
     * }
     */
    public static function identity(?string $plate): array
    {
        $normalized = self::normalize($plate);
        $user = $normalized !== '' ? self::findUser($plate) : null;

        if ($user !== null) {
            $department = $user->department?->departmentname
                ?? (filled($user->department_code) ? (string) $user->department_code : null);

            $registrationStatus = $user->isGranted()
                ? 'Registered'
                : (string) ($user->status ?: 'Registered');

            $vehicleDetails = self::vehicleDetailsForUserPlate($user, $normalized)
                ?? $user->vehicleType?->vehicle_name;

            return [
                'plate' => $normalized !== '' ? $normalized : strtoupper(trim((string) $plate)),
                'user_id' => $user->id,
                'visitor_id' => null,
                'owner_name' => $user->displayName(),
                'owner_label' => $user->displayName(),
                'id_number' => $user->id_number,
                'role' => $user->displayRoleLabel(),
                'purpose' => null,
                'registered' => true,
                'vehicle_details' => $vehicleDetails,
                'department' => $department,
                'registration_status' => $registrationStatus,
                'is_visitor' => false,
            ];
        }

        $visitor = $normalized !== '' ? self::findVisitor($plate) : null;

        if ($visitor !== null) {
            $statusLabel = $visitor->status === Visitor::STATUS_INSIDE ? 'Inside Campus' : (string) $visitor->status;

            return [
                'plate' => $normalized !== '' ? $normalized : strtoupper(trim((string) $plate)),
                'user_id' => null,
                'visitor_id' => $visitor->id,
                'owner_name' => $visitor->displayName(),
                'owner_label' => $visitor->displayName(),
                'id_number' => null,
                'role' => 'Visitor',
                'purpose' => $visitor->purpose,
                'registered' => true,
                'vehicle_details' => $visitor->vehicleType?->vehicle_name,
                'department' => $visitor->office_to_visit,
                'registration_status' => $statusLabel,
                'is_visitor' => true,
            ];
        }

        return [
            'plate' => $normalized !== '' ? $normalized : strtoupper(trim((string) $plate)),
            'user_id' => null,
            'visitor_id' => null,
            'owner_name' => null,
            'owner_label' => $normalized !== '' ? 'Unknown Vehicle' : null,
            'id_number' => null,
            'role' => null,
            'purpose' => null,
            'registered' => false,
            'vehicle_details' => null,
            'department' => null,
            'registration_status' => $normalized !== '' ? 'Plate Not Registered' : null,
            'is_visitor' => false,
        ];
    }

    /**
     * Map normalized plate → user id (short TTL; campus fleets are small).
     *
     * @return array<string, int|string>
     */
    private static function normalizedIndex(): array
    {
        return Cache::remember('plate_lookup:normalized_index', now()->addMinutes(2), function () {
            $map = [];
            User::query()
                ->whereNotNull('plate_number')
                ->where('plate_number', '!=', '')
                ->whereNotIn('plate_number', ['N/A', 'n/a', 'NA', 'NONE', 'None'])
                ->get(['id', 'plate_number'])
                ->each(function (User $user) use (&$map) {
                    $key = self::normalize((string) $user->plate_number);
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = $user->id;
                    }
                });

            \App\Models\UserVehicle::query()
                ->whereNotNull('plate_number')
                ->where('plate_number', '!=', '')
                ->get(['user_id', 'plate_number'])
                ->each(function (\App\Models\UserVehicle $row) use (&$map) {
                    $key = self::normalize((string) $row->plate_number);
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = $row->user_id;
                    }
                });

            return $map;
        });
    }

    public static function warmIndex(): void
    {
        self::normalizedIndex();
    }

    public static function forgetIndex(): void
    {
        Cache::forget('plate_lookup:normalized_index');
    }

    /**
     * Resolve vehicle type name for the specific plate on a multi-vehicle account.
     */
    private static function vehicleDetailsForUserPlate(User $user, string $normalizedPlate): ?string
    {
        if ($normalizedPlate === '') {
            return null;
        }

        $match = \App\Models\UserVehicle::query()
            ->with('vehicleType')
            ->where('user_id', $user->id)
            ->get()
            ->first(fn (\App\Models\UserVehicle $row) => self::normalize((string) $row->plate_number) === $normalizedPlate);

        return $match?->vehicleType?->vehicle_name;
    }
}
