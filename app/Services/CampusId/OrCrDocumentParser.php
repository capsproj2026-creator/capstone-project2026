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

        $hasLto = (bool) preg_match('/\blto\b/i', $raw)
            || (bool) preg_match('/land transportation/i', $raw);

        $hasDocumentKeyword = $kind === 'or'
            ? ((bool) preg_match('/official\s+receipt/i', $raw) || (bool) preg_match('/\bO\.?\s*R\.?\b/', $raw))
            : ((bool) preg_match('/certificate of registration/i', $raw)
                || (bool) preg_match('/\bC\.?\s*R\.?\b/', $raw)
                || (bool) preg_match('/cert(?:ificate)?\s+of\s+reg/i', $raw));

        $plate = $this->extractPlateNumber($raw);
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
            'has_lto' => $hasLto,
            'has_document_keyword' => $hasDocumentKeyword,
            'warnings' => $warnings,
        ];
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
