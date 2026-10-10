<?php

namespace App\Support;

use App\Models\ViolationSanction;
use Illuminate\Support\Collection;

class ViolationSanctionPresenter
{
    /**
     * @return Collection<int, ViolationSanction>
     */
    public static function all(): Collection
    {
        return ViolationSanction::query()
            ->orderBy('id')
            ->get()
            ->keyBy(fn (ViolationSanction $row) => (int) $row->id);
    }

    /**
     * Description for strike level 1–3 from seeded violation_sanctions.
     */
    public static function descriptionForStrike(int $strike): ?string
    {
        $sanction = self::sanctionForStrike($strike);

        return $sanction?->description ? trim((string) $sanction->description) : null;
    }

    public static function nameForStrike(int $strike): ?string
    {
        $sanction = self::sanctionForStrike($strike);

        return $sanction?->sanctions_name ? trim((string) $sanction->sanctions_name) : null;
    }

    /**
     * Short line for UI: "1st Offense — Issuance of warning ticket…"
     */
    public static function labelForStrike(int $strike): ?string
    {
        $name = self::nameForStrike($strike);
        $description = self::descriptionForStrike($strike);

        if ($name === null && $description === null) {
            return null;
        }

        if ($name && $description) {
            return "{$name} — {$description}";
        }

        return $name ?: $description;
    }

    /**
     * Sentence added to the owner's citation notice for the offense this citation reached.
     */
    public static function citationNotice(int $strikes): string
    {
        if ($strikes <= 1) {
            return '1st Offense — this is a warning ticket. No sanction applies.';
        }

        if (! app(\App\Services\ViolationEnforcementService::class)->endorsementsEnabled()) {
            return (self::labelForStrike($strikes) ?? '').'.';
        }

        if ($strikes === 2) {
            return '2nd Offense — Security has endorsed your case to the GSU for a six-month parking permit suspension. Nothing changes until the GSU approves it.';
        }

        return '3rd Offense — your case is endorsed to the GSU, which reviews it and endorses it to the VPAF for revocation of parking privileges. Nothing changes until the VPAF approves it.';
    }

    public static function sanctionForStrike(int $strike): ?ViolationSanction
    {
        $strike = max(1, min(3, $strike));
        $all = self::all();

        if ($all->has($strike)) {
            return $all->get($strike);
        }

        // Fallback: match by name (1st / 2nd / 3rd).
        $needle = match ($strike) {
            1 => '1st',
            2 => '2nd',
            default => '3rd',
        };

        return $all->first(function (ViolationSanction $row) use ($needle) {
            return str_contains(strtolower((string) $row->sanctions_name), strtolower($needle));
        });
    }
}
