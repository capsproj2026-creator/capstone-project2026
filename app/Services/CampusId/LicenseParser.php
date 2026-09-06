<?php

namespace App\Services\CampusId;

/**
 * Parse Philippine Driver's License OCR text into registration fields.
 */
class LicenseParser
{
    /** @var list<string> */
    private const SKIP_LINE_PATTERNS = [
        '/republic of the philippines/i',
        '/department of transportation/i',
        '/land transportation/i',
        '/\blto\b/i',
        '/driver\'?s?\s*license/i',
        '/\bnon[- ]professional\b/i',
        '/\bprofessional\b/i',
        '/\bnationality\b/i',
        '/\bsex\b/i',
        '/\bweight\b/i',
        '/\bheight\b/i',
        '/\beyes\b/i',
        '/\bblood\b/i',
        '/date of birth/i',
        '/\bexpiration\b/i',
        '/\bagency\s*code\b/i',
        '/\brestriction\b/i',
        '/\bconditions?\b/i',
        '/\bsignature\b/i',
        '/\bdl codes?\b/i',
        '/\blicense no\b/i',
        '/\blicenseno\b/i',
        '/\blicense\s*number\b/i',
        '/\blic\.?\s*no\b/i',
        '/\bdl\s*no\b/i',
        '/last name,\s*first name/i',
        '/organ donation/i',
        '/in case of emergency/i',
        '/i will not donate/i',
        '/same as above/i',
        '/serial number/i',
        '/^\s*phl\s*$/i',
        '/^\s*back\s*$/i',
        '/lto\s*[•·]\s*driver/i',
    ];

    /**
     * @param  list<array{text: string, confidence?: float|null, height?: float|null, center_y?: float|null}>  $lines
     * @return array{
     *     full_name: ?string,
     *     address: ?string,
     *     phone_number: ?string,
     *     driver_license_number: ?string,
     *     plate_number: ?string,
     *     warnings: list<string>
     * }
     */
    public function parse(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) ($line['text'] ?? '')) ?? '');
            if ($text === '') {
                continue;
            }
            $normalized[] = $text;
        }

        $rawText = implode("\n", $normalized);

        $licenseNumber = $this->extractLicenseNumber($rawText);
        $phoneNumber = $this->extractPhoneNumber($rawText);
        $fullName = $this->extractFullName($normalized);
        $address = $this->extractAddress($normalized, $fullName);
        $plateNumber = $this->extractPlateNumber($rawText, $licenseNumber);

        $warnings = [];
        if ($fullName === null) {
            $warnings[] = 'Could not read the full name. Please enter it manually.';
        }
        if ($address === null) {
            $warnings[] = 'Could not read the address. Please enter it manually.';
        }
        if ($licenseNumber === null) {
            $warnings[] = 'Could not read the driver\'s license number. Please enter it manually.';
        }

        return [
            'full_name' => $fullName,
            'address' => $address,
            'phone_number' => $phoneNumber,
            'driver_license_number' => $licenseNumber,
            'plate_number' => $plateNumber,
            'warnings' => $warnings,
        ];
    }

    private function extractLicenseNumber(string $rawText): ?string
    {
        $text = $this->normalizeLicenseOcrText($rawText);

        $fromLabel = $this->extractLabeledLicenseNumber($text);
        if ($fromLabel !== null) {
            return $fromLabel;
        }

        return $this->findLicenseNumberInText($text);
    }

    private function normalizeLicenseOcrText(string $rawText): string
    {
        $text = str_replace(["\u{2013}", "\u{2014}", "\u{2212}", '_'], '-', $rawText);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractLabeledLicenseNumber(string $text): ?string
    {
        $label = '(?:license\s*no\.?|licenseno|lic\.?\s*no\.?|license\s*number|dl\s*no\.?)';
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $index => $line) {
            if (! preg_match('/'.$label.'/i', $line)) {
                continue;
            }

            $rest = trim((string) preg_replace('/^.*?(?:'.$label.')\s*[:#.\-]*/i', '', $line));
            $candidate = $this->findLicenseNumberInText($rest);
            if ($candidate !== null) {
                return $candidate;
            }

            for ($j = $index + 1; $j < count($lines) && $j <= $index + 2; $j++) {
                $next = trim($lines[$j]);
                if ($next === '' || preg_match('/'.$label.'/i', $next)) {
                    continue;
                }

                $candidate = $this->findLicenseNumberInText($next);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function findLicenseNumberInText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        if (preg_match_all('/(?<![A-Z0-9])([A-Z][\dOI]{2}[\s.\-]*[\dOI]{2}[\s.\-]*[\dOI]{5,8})(?![\dOI])/i', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                $normalized = $this->normalizeLicenseNumber((string) $candidate);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if (preg_match_all('/(?<![A-Z0-9])([A-Z][\dOI]{9,12})(?![\dOI])/i', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                $normalized = $this->normalizeLicenseNumber((string) $candidate);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function normalizeLicenseNumber(string $raw): ?string
    {
        $value = strtoupper(trim($raw));
        if ($value === '' || ! preg_match('/^[A-Z]/', $value)) {
            return null;
        }

        $letter = $value[0];
        $rest = substr($value, 1);
        $rest = strtr($rest, ['O' => '0', 'I' => '1', 'L' => '1']);
        $digits = preg_replace('/\D+/', '', $rest) ?? '';

        if (strlen($digits) < 9 || strlen($digits) > 12) {
            return null;
        }

        return $letter.substr($digits, 0, 2).'-'.substr($digits, 2, 2).'-'.substr($digits, 4);
    }

    private function extractPlateNumber(string $rawText, ?string $licenseNumber): ?string
    {
        if (preg_match('/(?:plate|plate\s*no\.?)\s*[:#]?\s*([A-Z]{1,3}[-\s]?\d{3,4}[A-Z]?)/i', $rawText, $match)) {
            $candidate = strtoupper(trim($match[1]));
            if ($this->isLicenseNumberLookalike($candidate, $licenseNumber)) {
                return null;
            }

            return $candidate;
        }

        if (preg_match('/\b([A-Z]{3}[-\s]\d{3,4})\b/', $rawText, $match)) {
            $candidate = strtoupper(trim($match[1]));
            if ($this->isLicenseNumberLookalike($candidate, $licenseNumber)) {
                return null;
            }

            return $candidate;
        }

        return null;
    }

    private function isLicenseNumberLookalike(string $candidate, ?string $licenseNumber): bool
    {
        $compact = strtoupper(preg_replace('/[\s\-]/', '', $candidate) ?? '');
        $licenseCompact = strtoupper(preg_replace('/[\s\-]/', '', (string) $licenseNumber) ?? '');

        if ($licenseCompact !== '' && $compact === $licenseCompact) {
            return true;
        }

        return (bool) preg_match('/^[A-Z]\d{2}\d{2}\d{5,8}$/', $compact);
    }

    private function extractPhoneNumber(string $rawText): ?string
    {
        if (preg_match('/\b(09\d{9})\b/', $rawText, $match)) {
            return $match[1];
        }

        if (preg_match('/\b(?:\+?63)9(\d{9})\b/', $rawText, $match)) {
            return '09'.$match[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractFullName(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! $this->isLastFirstNameLine($line)) {
                continue;
            }
            if (preg_match('/^([A-Za-z][A-Za-z\'.\- ]+),\s*([A-Za-z][A-Za-z\'.\- ]+)$/u', $line, $match)) {
                $last = $this->titleCase($match[1]);
                $rest = $this->titleCase($match[2]);

                return trim($rest.' '.$last);
            }
        }

        foreach ($lines as $line) {
            if (! $this->looksLikeNameLine($line)) {
                continue;
            }

            $words = preg_split('/\s+/u', $line) ?: [];
            if (count($words) < 2) {
                continue;
            }

            return $this->titleCase($line);
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractAddress(array $lines, ?string $fullName): ?string
    {
        $parts = $this->collectLabeledAddressParts($lines);

        if ($parts === []) {
            $parts = $this->collectAddressAfterName($lines, $fullName);
        }

        if ($parts === []) {
            foreach ($lines as $line) {
                $stripped = $this->stripAddressLabel($line);
                if ($stripped === '' || $this->isLastFirstNameLine($stripped) || $this->isAddressStopLine($stripped)) {
                    continue;
                }
                if ($this->looksLikeAddressLine($stripped)) {
                    $parts[] = $stripped;
                }
            }
        }

        return $this->composeAddress($parts);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function collectLabeledAddressParts(array $lines): array
    {
        $parts = [];

        foreach ($lines as $index => $line) {
            if (! preg_match('/^add?r+e+s+s?\s*[:.\-]?\s*(.*)$/i', $line, $match)) {
                continue;
            }

            $rest = trim((string) ($match[1] ?? ''));
            if ($rest !== '' && ! $this->isAddressStopLine($rest) && ! $this->isLastFirstNameLine($rest)) {
                $parts[] = $rest;
            }

            for ($j = $index + 1; $j < count($lines) && $j <= $index + 4; $j++) {
                $next = $this->stripAddressLabel($lines[$j]);
                if ($next === '' || $this->isAddressStopLine($next) || $this->isLastFirstNameLine($next)) {
                    break;
                }
                if ($this->looksLikeAddressLine($next) || $this->looksLikePlaceLine($next)) {
                    $parts[] = $next;
                }
            }

            if ($parts !== []) {
                return $parts;
            }
        }

        return $parts;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function collectAddressAfterName(array $lines, ?string $fullName): array
    {
        $nameIndex = $this->findNameLineIndex($lines, $fullName);
        if ($nameIndex < 0) {
            return [];
        }

        $parts = [];
        $started = false;

        for ($i = $nameIndex + 1; $i < count($lines); $i++) {
            $line = $this->stripAddressLabel($lines[$i]);
            if ($line === '' || $this->isLastFirstNameLine($line)) {
                continue;
            }
            if ($this->isAddressStopLine($line)) {
                if ($started) {
                    break;
                }
                continue;
            }
            if ($this->looksLikeAddressLine($line) || ($started && $this->looksLikePlaceLine($line))) {
                $parts[] = $line;
                $started = true;
                continue;
            }
            if ($started) {
                break;
            }
        }

        return $parts;
    }

    /**
     * @param  list<string>  $parts
     */
    private function composeAddress(array $parts): ?string
    {
        $parts = $this->dedupeRawAddressLines($parts);
        $fragments = [];

        foreach ($parts as $part) {
            foreach ($this->splitAddressFragments($part) as $fragment) {
                $fragments[] = $fragment;
            }
        }

        $fragments = $this->dedupeAddressFragments($fragments);
        if ($fragments === []) {
            return null;
        }

        return mb_strtoupper(implode(', ', $fragments), 'UTF-8');
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function dedupeRawAddressLines(array $lines): array
    {
        $kept = [];

        foreach ($lines as $line) {
            $compact = $this->compactAddressToken($this->normalizeOcrAddressFragment($line));
            if (strlen($compact) < 4) {
                $kept[] = $line;
                continue;
            }

            $replaced = false;
            foreach ($kept as $index => $existing) {
                $existingCompact = $this->compactAddressToken($this->normalizeOcrAddressFragment($existing));
                if ($this->isOverlappingAddressToken($compact, $existingCompact)) {
                    if (strlen($compact) > strlen($existingCompact)) {
                        $kept[$index] = $line;
                    }
                    $replaced = true;
                    break;
                }
            }

            if (! $replaced) {
                $kept[] = $line;
            }
        }

        return $kept;
    }

    /**
     * @return list<string>
     */
    private function splitAddressFragments(string $line): array
    {
        $normalized = $this->normalizeOcrAddressFragment($line);
        $chunks = preg_split('/\s*,\s*/u', $normalized) ?: [];
        $fragments = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk, " \t-");
            if ($chunk === '') {
                continue;
            }
            foreach ($this->splitTrailingPlace($chunk) as $piece) {
                $fragments[] = $piece;
            }
        }

        return $fragments;
    }

    /**
     * @return list<string>
     */
    private function splitTrailingPlace(string $chunk): array
    {
        if (preg_match('/^(.*?)[\s,]+(\d{4})$/u', $chunk, $zipMatch)) {
            $head = trim((string) $zipMatch[1]);
            $zip = (string) $zipMatch[2];
            $split = $head !== '' ? $this->splitTrailingPlace($head) : [];
            $split[] = $zip;

            return $split;
        }

        $places = [
            'CAMARINES SUR', 'CAMARINES NORTE', 'CATANDUANES', 'MASBATE', 'SORSOGON', 'ALBAY',
            'NAGA CITY', 'LOURDES YOUNG', 'NABUA', 'IRIGA', 'PILI', 'BATO', 'BUHI', 'BAAO', 'BULA', 'NAGA',
        ];

        $upper = mb_strtoupper($chunk, 'UTF-8');
        foreach ($places as $place) {
            if ($upper === $place) {
                return [$place];
            }
            $suffix = ' '.$place;
            if (str_ends_with($upper, $suffix) && strlen($upper) > strlen($suffix)) {
                $head = trim(substr($chunk, 0, strlen($chunk) - strlen($suffix)));
                if ($head === '') {
                    return [$place];
                }

                return array_merge($this->splitTrailingPlace($head), [$place]);
            }
        }

        return [$chunk];
    }

    /**
     * @param  list<string>  $fragments
     * @return list<string>
     */
    private function dedupeAddressFragments(array $fragments): array
    {
        $kept = [];

        foreach ($fragments as $fragment) {
            $fragment = trim($fragment, " \t,.");
            if ($fragment === '') {
                continue;
            }

            $compact = $this->compactAddressToken($fragment);
            if ($compact === '') {
                continue;
            }

            $replaced = false;
            foreach ($kept as $index => $existing) {
                $existingCompact = $this->compactAddressToken($existing);
                if ($this->isOverlappingAddressToken($compact, $existingCompact)) {
                    if (strlen($compact) > strlen($existingCompact)) {
                        $kept[$index] = $fragment;
                    }
                    $replaced = true;
                    break;
                }
            }

            if (! $replaced) {
                $kept[] = $fragment;
            }
        }

        return $kept;
    }

    private function isOverlappingAddressToken(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $short = strlen($a) <= strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;
        if (strlen($short) >= 8 && (str_starts_with($long, $short) || str_ends_with($long, $short))) {
            $ratio = strlen($short) / max(strlen($long), 1);

            return $ratio >= 0.72;
        }

        similar_text($a, $b, $percent);

        return $percent >= 86 && (min(strlen($a), strlen($b)) / max(strlen($a), strlen($b), 1)) >= 0.72;
    }

    private function normalizeOcrAddressFragment(string $line): string
    {
        $text = preg_replace('/(?<=[A-Za-z0-9])\.(?=[A-Za-z0-9])/u', ' ', $line) ?? $line;
        $text = str_ireplace(
            ['LOURDESYOUNG', 'CAMARINESSUR', 'CAMARINESNORTE', 'NAGACITY'],
            ['LOURDES YOUNG', 'CAMARINES SUR', 'CAMARINES NORTE', 'NAGA CITY'],
            $text
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function compactAddressToken(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/u', '', $value) ?? '');
    }

    /**
     * @param  list<string>  $lines
     */
    private function findNameLineIndex(array $lines, ?string $fullName): int
    {
        foreach ($lines as $index => $line) {
            if ($this->isLastFirstNameLine($line)) {
                return $index;
            }
        }

        if ($fullName === null) {
            return -1;
        }

        $compact = mb_strtolower($this->compactName($fullName), 'UTF-8');
        foreach ($lines as $index => $line) {
            if (mb_strtolower($this->compactName($line), 'UTF-8') === $compact) {
                return $index;
            }
        }

        return -1;
    }

    private function stripAddressLabel(string $line): string
    {
        $stripped = preg_replace('/^add?r+e+s+s?\s*[:.\-]?\s*/i', '', $line) ?? $line;

        return trim($stripped);
    }

    private function isLastFirstNameLine(string $line): bool
    {
        if (! preg_match('/^([A-Za-z][A-Za-z\'.\- ]+),\s*([A-Za-z][A-Za-z\'.\- ]+)$/u', $line)) {
            return false;
        }

        return ! preg_match('/\b(brgy|barangay|zone|city|province|street|purok|sitio|camarines|albay|sorsogon|nabua|naga|iriga|pili|bato|buhi|baao|bula)\b/i', $line);
    }

    private function looksLikeAddressLine(string $line): bool
    {
        if ($this->isAddressStopLine($line)) {
            return false;
        }

        if (preg_match('/\b(brgy|barangay|bgy|street|st\.|ave|avenue|road|rd\.|city|province|zone|purok|sitio|blk|block|lot|subd|subdivision|village|poblacion|municipal)\b/i', $line)) {
            return true;
        }

        if (preg_match('/\b(camarines|albay|sorsogon|catanduanes|masbate|nabua|naga|iriga|pili|bato|buhi|baao|bula)\b/i', $line)) {
            return true;
        }

        if (preg_match('/\d/', $line) && preg_match('/[A-Za-z]{3,}/', $line)) {
            return true;
        }

        return substr_count($line, ',') >= 2 && str_word_count($line) >= 3;
    }

    private function looksLikePlaceLine(string $line): bool
    {
        if ($this->isAddressStopLine($line) || preg_match('/\d{2}[\/\-]\d{2}/', $line)) {
            return false;
        }

        $words = str_word_count($line);

        return $words >= 1 && $words <= 8 && (bool) preg_match('/^[A-Za-z][A-Za-z\s.,\'-]{2,}$/u', $line);
    }

    private function isAddressStopLine(string $line): bool
    {
        if ($this->extractLicenseNumber($line) !== null) {
            return true;
        }

        if ($this->extractPhoneNumber($line) !== null && ! preg_match('/[A-Za-z]{4,}/', $line)) {
            return true;
        }

        if (preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $line)) {
            return true;
        }

        if (preg_match('/\b\d{4}\/\d{2}\/\d{2}\b/', $line)) {
            return true;
        }

        foreach (self::SKIP_LINE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    private function compactName(string $value): string
    {
        return preg_replace('/[\s,]+/u', ' ', trim($value)) ?? trim($value);
    }

    private function looksLikeNameLine(string $text): bool
    {
        if (! preg_match('/^[A-Za-z][A-Za-z\s\'.-]+$/u', $text)) {
            return false;
        }

        foreach (self::SKIP_LINE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return false;
            }
        }

        if (preg_match('/\d/', $text)) {
            return false;
        }

        return str_word_count($text) >= 2;
    }

    private function titleCase(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
