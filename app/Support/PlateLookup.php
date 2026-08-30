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
     * Fix common OCR confusions (O/0, I/1, B/8) using PH plate letter/digit slots.
     *
     * @return list<string>
     */
    public static function ocrCorrectionVariants(?string $plate): array
    {
        $normalized = self::normalize($plate);
        if ($normalized === '') {
            return [];
        }

        $variants = [$normalized];

        if (preg_match('/^[A-Z0-9]{10,12}$/', $normalized) && preg_match('/[OILBSZG]/', $normalized)) {
            $digits = strtr($normalized, [
                'O' => '0', 'Q' => '0', 'D' => '0',
                'I' => '1', 'L' => '1',
                'Z' => '2', 'S' => '5', 'B' => '8', 'G' => '6',
            ]);
            if ($digits !== $normalized) {
                $variants[] = $digits;
            }
        }

        if (preg_match('/^\d{11}$/', $normalized)) {
            $digits = strtr($normalized, [
                'O' => '0', 'Q' => '0', 'D' => '0',
                'I' => '1', 'L' => '1',
                'Z' => '2', 'S' => '5', 'B' => '8', 'G' => '6',
            ]);
            $variants[] = $digits;
        } elseif (preg_match('/^([A-Z]{2,3})(\d{3,4})$/', $normalized, $m)) {
            $letters = strtr($m[1], ['0' => 'O', '1' => 'I', '2' => 'Z', '5' => 'S', '6' => 'G', '8' => 'B']);
            $digits = strtr($m[2], ['O' => '0', 'I' => '1', 'L' => '1', 'Z' => '2', 'S' => '5', 'B' => '8', 'G' => '6']);
            $variants[] = $letters.$digits;
        }

        $confusable = [
            '0' => 'O', 'O' => '0',
            '1' => 'I', 'I' => '1',
            '8' => 'B', 'B' => '8',
            '5' => 'S', 'S' => '5',
            '2' => 'Z', 'Z' => '2',
        ];
        for ($i = 0; $i < strlen($normalized); $i++) {
            $ch = $normalized[$i];
            if (! isset($confusable[$ch])) {
                continue;
            }
            $alt = $confusable[$ch];
            $variants[] = substr($normalized, 0, $i).$alt.substr($normalized, $i + 1);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * All normalized keys to try when resolving an OCR read.
     *
     * @return list<string>
     */
    public static function searchKeys(?string $plate): array
    {
        $keys = [];
        foreach (self::ocrCorrectionVariants($plate) as $variant) {
            $normalized = self::normalize($variant);
            if ($normalized !== '') {
                $keys[] = $normalized;
            }
        }

        return array_values(array_unique($keys));
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
        foreach (self::searchKeys($plate) as $normalized) {
            $candidates = self::candidates($normalized);
            if ($candidates === []) {
                continue;
            }

            $user = User::query()
                ->with(['role', 'vehicleType', 'department'])
                ->whereIn('plate_number', $candidates)
                ->first();

            if ($user) {
                return $user;
            }

            $index = self::normalizedIndex();
            $userId = $index[$normalized] ?? null;
            if ($userId !== null) {
                return User::query()->with(['role', 'vehicleType', 'department'])->where('id', $userId)->first();
            }
        }

        return self::findUserFuzzy($plate);
    }

    /**
     * Last-resort match when OCR is off by one character.
     */
    public static function findUserFuzzy(?string $plate, int $maxDistance = 1): ?User
    {
        $normalized = self::normalize($plate);
        if ($normalized === '' || strlen($normalized) < 6) {
            return null;
        }

        if (! preg_match('/^([A-Z]{2,3}\d{3,4}|\d{11})$/', $normalized)) {
            return null;
        }

        $index = self::normalizedIndex();
        $bestId = null;
        $bestDistance = $maxDistance + 1;

        foreach ($index as $registered => $userId) {
            if (abs(strlen($registered) - strlen($normalized)) > $maxDistance) {
                continue;
            }
            $distance = levenshtein($normalized, $registered);
            if ($distance <= $maxDistance && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestId = $userId;
            }
        }

        if ($bestId === null) {
            return null;
        }

        return User::query()->with(['role', 'vehicleType', 'department'])->where('id', $bestId)->first();
    }

    public static function findVisitor(?string $plate): ?Visitor
    {
        foreach (self::searchKeys($plate) as $normalized) {
            if ($normalized === '') {
                continue;
            }

            $candidates = self::candidates($normalized);

            $visitor = Visitor::query()
                ->with('vehicleType')
                ->whereIn('status', Visitor::ACTIVE_STATUSES)
                ->whereIn('plate_number', $candidates)
                ->orderByDesc('id')
                ->first();

            if ($visitor) {
                return $visitor;
            }

            $visitor = Visitor::query()
                ->with('vehicleType')
                ->whereIn('status', Visitor::ACTIVE_STATUSES)
                ->orderByDesc('id')
                ->get()
                ->first(fn (Visitor $v) => self::normalize((string) $v->plate_number) === $normalized);

            if ($visitor) {
                return $visitor;
            }
        }

        return null;
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
            $resolvedPlate = self::normalize((string) $user->plate_number) ?: $normalized;
            $department = $user->department?->departmentname
                ?? (filled($user->department_code) ? (string) $user->department_code : null);

            $registrationStatus = $user->isGranted()
                ? 'Registered'
                : (string) ($user->status ?: 'Registered');

            $vehicleDetails = self::vehicleDetailsForUserPlate($user, $resolvedPlate)
                ?? $user->vehicleType?->vehicle_name;

            return [
                'plate' => $resolvedPlate !== '' ? $resolvedPlate : strtoupper(trim((string) $plate)),
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
