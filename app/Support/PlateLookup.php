<?php

namespace App\Support;

use App\Models\User;
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

        return array_values(array_unique($out));
    }

    public static function findUser(?string $plate): ?User
    {
        $candidates = self::candidates($plate);
        if ($candidates === []) {
            return null;
        }

        $user = User::query()
            ->with('role')
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

        return User::query()->with('role')->where('id', $userId)->first();
    }

    /**
     * @return array{plate: string, user_id: int|string|null, owner_name: string|null, id_number: string|null, role: string|null, registered: bool}
     */
    public static function identity(?string $plate): array
    {
        $normalized = self::normalize($plate);
        $user = $normalized !== '' ? self::findUser($plate) : null;

        return [
            'plate' => $normalized !== '' ? $normalized : strtoupper(trim((string) $plate)),
            'user_id' => $user?->id,
            'owner_name' => $user?->displayName(),
            'id_number' => $user?->id_number,
            'role' => $user?->roleName(),
            'registered' => $user !== null,
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

            return $map;
        });
    }

    public static function forgetIndex(): void
    {
        Cache::forget('plate_lookup:normalized_index');
    }
}
