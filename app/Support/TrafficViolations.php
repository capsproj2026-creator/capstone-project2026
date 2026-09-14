<?php

namespace App\Support;

use App\Models\ViolationType;
use App\Services\SequenceService;

/**
 * Canonical campus traffic violations (CSPC Ref. No. 2026-021).
 * The system must expose exactly these four types.
 */
class TrafficViolations
{
    /**
     * @return list<array{violation_name: string, description: string, status: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'violation_name' => 'Wrong Parking',
                'description' => 'Vehicles are not parked at the designated parking area.',
                'status' => 'Active',
            ],
            [
                'violation_name' => 'Over Speeding',
                'description' => 'The driver has violated the approved speed limit within the College premises, which is 15 kph.',
                'status' => 'Active',
            ],
            [
                'violation_name' => 'Use of Motorcycle Mufflers',
                'description' => 'Mufflers are strictly prohibited inside the College premises.',
                'status' => 'Active',
            ],
            [
                'violation_name' => 'Explicit disrespect to Security Personnel implementing the Policy',
                'description' => 'Explicit disrespect to Security Personnel implementing the Policy.',
                'status' => 'Active',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::definitions(), 'violation_name');
    }

    /**
     * Sync MongoDB violation_types to the four official entries only.
     */
    public static function syncToDatabase(): void
    {
        $names = self::names();
        $keptIds = [];

        foreach (self::definitions() as $index => $type) {
            $id = $index + 1;
            $keptIds[] = $id;
            ViolationType::query()->updateOrCreate(['id' => $id], $type);
        }

        // Remove legacy / AI-only types that are no longer part of the official list.
        ViolationType::query()
            ->where(function ($q) use ($names, $keptIds): void {
                $q->whereNotIn('id', $keptIds)
                    ->orWhereNotIn('violation_name', $names);
            })
            ->whereNotIn('id', $keptIds)
            ->delete();

        ViolationType::query()
            ->whereNotIn('violation_name', $names)
            ->delete();
    }

    /**
     * @param  list<string>|string|null  $types
     */
    public static function displayLabel(array|string|null $types): string
    {
        if (is_string($types)) {
            return trim($types);
        }

        if (! is_array($types) || $types === []) {
            return '';
        }

        return implode(' · ', array_values(array_filter(array_map(
            static fn ($t) => trim((string) $t),
            $types
        ))));
    }
}
