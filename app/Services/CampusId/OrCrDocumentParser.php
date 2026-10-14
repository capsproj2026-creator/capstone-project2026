<?php

namespace App\Services\CampusId;

/**
 * Keyword sanity checks for LTO Official Receipt (OR) and Certificate of Registration (CR).
 * OCR is assistive — results are warnings for the applicant to review, not hard failures.
 */
class OrCrDocumentParser
{
    /**
     * @param  list<array{text: string, confidence?: float|null}|string>  $lines
     * @return array{
     *     plate_number: ?string,
     *     owner_name: ?string,
     *     has_lto: bool,
     *     has_document_keyword: bool,
     *     warnings: list<string>
     * }
     */
    public function parse(array $lines, string $kind, ?string $expectedPlate = null): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $text = is_array($line) ? (string) ($line['text'] ?? '') : (string) $line;
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        $raw = implode("\n", $normalized);
        $kind = strtolower(trim($kind)) === 'cr' ? 'cr' : 'or';

        // OCR often drops spaces between words ("LANDTRANSPORTATION OFFICE", "OFFICIALRECEIPT").
        $hasLto = (bool) preg_match('/\blto\b/i', $raw)
            || (bool) preg_match('/land\s*transportation/i', $raw);

        $hasDocumentKeyword = $kind === 'or'
            ? ((bool) preg_match('/official\s*receipt/i', $raw) || (bool) preg_match('/\bO\.?\s*R\.?\b/', $raw))
            : ((bool) preg_match('/certificate\s*of\s*registration/i', $raw)
                || (bool) preg_match('/\bC\.?\s*R\.?\b/', $raw)
                || (bool) preg_match('/cert(?:ificate)?\s*of\s*reg/i', $raw));

        $ownerName = $this->extractOwnerName($raw);
        $cr = $kind === 'cr' ? $this->extractCrVehicle($raw) : [];
        $plate = $cr['plate'] ?? $this->extractPlateNumber($raw);
        $series = $cr['series'] ?? null;
        $body = $cr['body'] ?? null;
        $model = null;
        $bodyAlreadyInSeries = $series && $body && preg_match('/\b'.preg_quote($body, '/').'\b/', $series) === 1;
        if ($series && $body && ! $bodyAlreadyInSeries) {
            $model = $series.' '.$body;
        } elseif ($series || $body) {
            $model = $series ?: $body;
        }
        $color = $cr['color'] ?? null;
        $warnings = [];

        if (! $hasLto) {
            $warnings[] = 'Could not confirm “LTO” on this file. Please review before submitting.';
        }

        if (! $hasDocumentKeyword) {
            $warnings[] = $kind === 'or'
                ? 'Could not find “OFFICIAL RECEIPT” on this file. Confirm it is the LTO OR.'
                : 'Could not find “CERTIFICATE OF REGISTRATION” on this file. Confirm it is the LTO CR.';
        }

        $expected = $this->normalizePlate((string) $expectedPlate);
        $found = $this->normalizePlate((string) $plate);
        if ($expected !== '' && $found !== '' && $expected !== $found) {
            $warnings[] = 'Plate on this document ('.$plate.') does not match the form ('.$expectedPlate.'). Please review.';
        }

        return [
            'plate_number' => $plate,
            'owner_name' => $ownerName,
            'vehicle_model' => $model,
            'vehicle_color' => $color,
            'has_lto' => $hasLto,
            'has_document_keyword' => $hasDocumentKeyword,
            'warnings' => $warnings,
        ];
    }

    public function extractOwnerName(string $rawText): ?string
    {
        if (preg_match('/owner\'?s?\s*name\s*[:.\-]?\s*([A-Za-z][A-Za-z\s,.\']{4,80})/i', $rawText, $match)) {
            $name = $this->cleanOwnerName($match[1]);
            if ($name !== null) {
                return $name;
            }
        }

        if (preg_match('/\b([A-Z]{2,}(?:\s+[A-Z]{2,}){0,2}),\s*([A-Z][A-Z\s.]{3,60})/', $rawText, $match)) {
            $name = $this->cleanOwnerName($match[1].', '.$match[2]);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    private function cleanOwnerName(string $raw): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $name = trim($name, " :.-");
        $name = preg_replace('/\b(address|plate|engine|chassis|file|make|series)\b.*$/i', '', $name) ?? $name;
        $name = trim($name, " :,-");
        if (strlen($name) < 5 || ! preg_match('/[A-Za-z]{2,}/', $name)) {
            return null;
        }

        if (preg_match('/\b(city|metro|manila|address|street|st\.?|barangay|quezon|camarines|village|west)\b/i', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * LTO CR prints the vehicle as Series + Body Type, plus Color and Plate No.
     * Year Model and Make are separate boxes and are not part of the model.
     *
     * @return array{plate: ?string, series: ?string, body: ?string, color: ?string}
     */
    private function extractCrVehicle(string $rawText): array
    {
        $text = strtoupper($rawText);
        $labels = [
            '/(?<![A-Z])YEAR\s*MODEL/i' => "\nYEAR MODEL ",
            '/(?<![A-Z])TYPE\s*OF\s*BODY/i' => "\nTYPE OF BODY ",
            '/(?<![A-Z])BODY\s*TYPE/i' => "\nBODY TYPE ",
            '/(?<![A-Z])PLATE\s*(?:NO\.?|NUMBER|#)/i' => "\nPLATE NO ",
            '/(?<![A-Z])SERIES/i' => "\nSERIES ",
            '/(?<![A-Z])COLOU?R/i' => "\nCOLOR ",
        ];
        foreach ($labels as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        $keys = [
            'YEAR MODEL' => 'year',
            'TYPE OF BODY' => 'body',
            'BODY TYPE' => 'body',
            'PLATE NO' => 'plate',
            'SERIES' => 'series',
            'COLOR' => 'color',
        ];
        $parts = preg_split('/\n(YEAR MODEL|TYPE OF BODY|BODY TYPE|PLATE NO|SERIES|COLOR)\s*/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $fields = ['plate' => null, 'series' => null, 'body' => null, 'color' => null];

        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $key = $keys[$parts[$i]] ?? null;
            if ($key === null || $key === 'year' || $fields[$key] !== null) {
                continue;
            }
            $fields[$key] = $this->cleanCrValue($key, (string) $parts[$i + 1]);
        }

        return $fields;
    }

    private function cleanCrValue(string $key, string $chunk): ?string
    {
        $line = '';
        foreach (preg_split('/\R/u', $chunk) ?: [] as $candidate) {
            $candidate = trim($candidate, " \t:.-#");
            if ($candidate !== '') {
                $line = $candidate;
                break;
            }
        }
        if ($line === '') {
            return null;
        }

        return match ($key) {
            'plate' => $this->plateFromText($line),
            'series' => $this->cleanSeries($line),
            'body' => $this->cleanBodyType($line),
            'color' => $this->cleanColor($line),
            default => null,
        };
    }

    private function cleanSeries(string $value): ?string
    {
        $value = preg_replace('/\b(BODY|TYPE|YEAR|MODEL|COLOR|COLOUR|MAKE|PLATE|CLASSIFICATION|FUEL|ENGINE|CHASSIS)\b.*$/i', '', $value) ?? $value;
        $value = strtoupper(trim((string) preg_replace('/\s+/u', ' ', $value)));
        $value = trim($value, " :.-");
        if ($value === '' || preg_match('/^(19|20)\d{2}$/', $value) || ! preg_match('/[A-Z]/', $value)) {
            return null;
        }
        if (strlen($value) < 2 || strlen($value) > 30) {
            return null;
        }

        return $value;
    }

    private function cleanBodyType(string $value): ?string
    {
        $value = strtoupper(trim((string) preg_replace('/[^A-Z\s-]/', ' ', $value)));
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        $types = [
            'SPORTS UTILITY VEHICLE',
            'SPORT UTILITY',
            'UTILITY VEHICLE',
            'PICK-UP',
            'PICK UP',
            'PICKUP',
            'HATCHBACK',
            'MOTORCYCLE',
            'MOTORYCLE',
            'MOPED',
            'SCOOTER',
            'JEEPNEY',
            'CONVERTIBLE',
            'SEDAN',
            'WAGON',
            'COUPE',
            'SUV',
            'AUV',
            'VAN',
            'BUS',
            'TRUCK',
            'TRAILER',
        ];
        foreach ($types as $type) {
            if ($value === $type || str_starts_with($value, $type.' ')) {
                return match ($type) {
                    'PICK UP', 'PICKUP' => 'PICK-UP',
                    'MOTORYCLE' => 'MOTORCYCLE',
                    default => $type,
                };
            }
        }

        return null;
    }

    private function cleanColor(string $value): ?string
    {
        $value = preg_replace('/\b(CLASSIFICATION|FUEL|MAKE|SERIES|BODY|YEAR|MODEL|OWNER|ADDRESS|PRIVATE|PUBLIC|GASOLINE|DIESEL)\b.*$/i', '', $value) ?? $value;
        $value = strtoupper(trim((string) preg_replace('/[^A-Za-z\s-]/', ' ', $value)));
        $words = array_values(array_filter(preg_split('/\s+/', $value) ?: []));
        $words = array_slice($words, 0, 3);
        if ($words === []) {
            return null;
        }
        $color = implode(' ', $words);
        if (strlen($color) < 3) {
            return null;
        }

        return $color;
    }

    private function plateFromText(string $value): ?string
    {
        $value = strtoupper($value);
        $patterns = [
            '/\b([A-Z]{3})\s*-?\s*(\d{3,4})\b/',
            '/\b(\d{3,4})\s*-?\s*([A-Z]{2,3})\b/',
            '/\b([A-Z]{2})\s*-?\s*(\d{4})\b/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $match)) {
                return $match[1].' '.$match[2];
            }
        }

        return null;
    }

    public function extractPlateNumber(string $rawText): ?string
    {
        if (preg_match('/(?:plate|plate\s*no\.?|mv\s*file)\s*[:#]?\s*([A-Z]{1,3}[-\s]?\d{3,4}[A-Z]?)/i', $rawText, $match)) {
            return strtoupper(trim($match[1]));
        }

        if (preg_match('/\b([A-Z]{3}[-\s]\d{3,4})\b/', $rawText, $match)) {
            return strtoupper(trim($match[1]));
        }

        return null;
    }

    private function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', trim($plate)) ?? '');
    }
}
