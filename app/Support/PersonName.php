<?php

namespace App\Support;

/**
 * Compare personal names from a school ID and a driver's license.
 *
 * Printed order and format do not matter. These are the same person:
 * "Joshua Fuertes Sabater", "Sabater, Joshua Fuertes", "SABATER, JOSHUA, FUERTES",
 * "Joshua F. Sabater", and "Joshua Sabater Jr."
 */
final class PersonName
{
    /** @var list<string> */
    private const SKIP = [
        'DE', 'DEL', 'DELA', 'DELOS', 'DELAS', 'DA', 'DAS', 'DOS',
        'LA', 'LAS', 'LOS', 'SAN', 'SANTA', 'STA', 'STO', 'VAN', 'VON', 'Y',
        'JR', 'SR', 'II', 'III', 'IV',
        'SN', 'ID', 'NAME', 'STUDENT', 'FACULTY', 'EMPLOYEE',
    ];

    /** @var list<string> */
    private const PARTICLE_PREFIXES = ['DELOS', 'DELAS', 'DELA', 'SANTA', 'DEL', 'DE'];

    public static function same(string $left, string $right): bool
    {
        $a = self::tokens($left);
        $b = self::tokens($right);
        if ($a === [] || $b === []) {
            return false;
        }

        [$usedA, $usedB] = self::pair($a, $b);
        $shorter = count($a) <= count($b) ? $a : $b;
        $used = count($a) <= count($b) ? $usedA : $usedB;
        $partsMatched = max(count($usedA), count($usedB));

        if (count($used) !== count($shorter) || $partsMatched < 2) {
            return false;
        }

        foreach ($shorter as $index => $token) {
            if (isset($used[$index]) && strlen($token) >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $value): array
    {
        $folded = self::fold($value);
        $parts = preg_split('/\s+/', trim($folded)) ?: [];
        $out = [];

        foreach ($parts as $part) {
            if ($part === '' || in_array($part, self::SKIP, true)) {
                continue;
            }
            if (strlen($part) < 2 && ! preg_match('/^[A-Z]$/', $part)) {
                continue;
            }
            $out[] = $part;
        }

        return array_values($out);
    }

    private static function fold(string $value): string
    {
        $value = strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c',
        ]);
        $value = strtoupper($value);

        return (string) preg_replace('/[^A-Z]+/', ' ', $value);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array{0: array<int, true>, 1: array<int, true>}
     */
    private static function pair(array $a, array $b): array
    {
        $usedA = [];
        $usedB = [];
        $order = array_keys($a);
        usort($order, fn (int $i, int $j): int => strlen($a[$j]) <=> strlen($a[$i]));

        foreach ($order as $i) {
            $bestJ = null;
            $bestScore = 0;
            foreach ($b as $j => $token) {
                if (isset($usedB[$j])) {
                    continue;
                }
                $score = self::score($a[$i], $token);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestJ = $j;
                }
            }
            if ($bestJ !== null) {
                $usedA[$i] = true;
                $usedB[$bestJ] = true;
            }
        }

        self::absorbGlued($a, $b, $usedA, $usedB);
        self::absorbGlued($b, $a, $usedB, $usedA);

        return [$usedA, $usedB];
    }

    private static function score(string $left, string $right): int
    {
        if ($left === $right) {
            return 100;
        }
        if (strlen($left) >= 3 && strlen($right) >= 3 && self::collapse($left) === self::collapse($right)) {
            return 90;
        }
        if (self::particleForm($left, $right) || self::particleForm($right, $left)) {
            return 80;
        }
        if (($left === 'MA' && $right === 'MARIA') || ($left === 'MARIA' && $right === 'MA')) {
            return 75;
        }

        $distance = levenshtein($left, $right);
        if ($distance === 1 && strlen($left) >= 6 && strlen($right) >= 6) {
            return 70;
        }
        if ($distance === 2 && strlen($left) >= 10 && strlen($right) >= 10) {
            return 65;
        }
        if (self::isInitial($left, $right) || self::isInitial($right, $left)) {
            return 60;
        }
        if (self::isSingleLetterEdit($left, $right)) {
            return 55;
        }
        if (self::isTruncation($left, $right)) {
            return 50;
        }

        return 0;
    }

    private static function collapse(string $token): string
    {
        return preg_replace('/(.)\1+/', '$1', $token) ?? $token;
    }

    private static function particleForm(string $longer, string $shorter): bool
    {
        if (strlen($shorter) < 4 || strlen($longer) <= strlen($shorter)) {
            return false;
        }

        foreach (self::PARTICLE_PREFIXES as $prefix) {
            if (str_starts_with($longer, $prefix) && substr($longer, strlen($prefix)) === $shorter) {
                return true;
            }
        }

        return false;
    }

    private static function isInitial(string $initial, string $word): bool
    {
        return strlen($initial) === 1 && strlen($word) >= 2 && $word[0] === $initial;
    }

    /**
     * One inserted letter, such as Jon / John. Limited to short given names so
     * Santos / Santo and Maria / Mario stay different people.
     */
    private static function isSingleLetterEdit(string $a, string $b): bool
    {
        if (abs(strlen($a) - strlen($b)) !== 1) {
            return false;
        }

        $shorter = strlen($a) < strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;
        if (strlen($shorter) < 3 || strlen($shorter) > 4) {
            return false;
        }

        $matched = 0;
        $length = strlen($longer);
        for ($i = 0; $i < $length && $matched < strlen($shorter); $i++) {
            if ($longer[$i] === $shorter[$matched]) {
                $matched++;
            }
        }

        return $matched === strlen($shorter);
    }

    private static function isTruncation(string $a, string $b): bool
    {
        if (strlen($a) === strlen($b)) {
            return false;
        }

        $shorter = strlen($a) < strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;
        if (! str_starts_with($longer, $shorter)) {
            return false;
        }

        $diff = strlen($longer) - strlen($shorter);
        if (strlen($shorter) >= 6 && $diff <= 2) {
            return true;
        }

        return strlen($shorter) === 3 && $diff === 1;
    }

    /**
     * OCR sometimes glues several name parts into one token ("JOSHUAFUERTESSABATER").
     *
     * @param  list<string>  $host
     * @param  list<string>  $parts
     * @param  array<int, true>  $usedHost
     * @param  array<int, true>  $usedParts
     */
    private static function absorbGlued(array $host, array $parts, array &$usedHost, array &$usedParts): void
    {
        $unusedParts = [];
        foreach ($parts as $j => $part) {
            if (! isset($usedParts[$j]) && strlen($part) >= 3) {
                $unusedParts[] = $j;
            }
        }

        foreach ($host as $i => $token) {
            if (isset($usedHost[$i]) || strlen($token) < 6 || $unusedParts === []) {
                continue;
            }

            $match = self::matchingConcat($token, $parts, $unusedParts);
            if ($match === null) {
                continue;
            }

            $usedHost[$i] = true;
            foreach ($match as $j) {
                $usedParts[$j] = true;
            }
            $unusedParts = array_values(array_filter(
                $unusedParts,
                fn (int $j): bool => ! in_array($j, $match, true)
            ));
        }
    }

    /**
     * @param  list<string>  $parts
     * @param  list<int>  $indexes
     * @return list<int>|null
     */
    private static function matchingConcat(string $token, array $parts, array $indexes): ?array
    {
        $count = count($indexes);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }
                $pair = [$indexes[$i], $indexes[$j]];
                $glued = $parts[$pair[0]].$parts[$pair[1]];
                if (self::sameSpelling($token, $glued)) {
                    return $pair;
                }
                for ($k = 0; $k < $count; $k++) {
                    if ($k === $i || $k === $j) {
                        continue;
                    }
                    $triple = [$indexes[$i], $indexes[$j], $indexes[$k]];
                    if (self::sameSpelling($token, $glued.$parts[$indexes[$k]])) {
                        return $triple;
                    }
                }
            }
        }

        return null;
    }

    private static function sameSpelling(string $left, string $right): bool
    {
        return $left === $right || (strlen($left) >= 6 && strlen($right) >= 6 && self::collapse($left) === self::collapse($right));
    }
}
