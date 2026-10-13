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

        $plate = $this->extractPlateNumber($raw);
        $ownerName = $this->extractOwnerName($raw);
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
