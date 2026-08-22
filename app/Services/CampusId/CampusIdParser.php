<?php

namespace App\Services\CampusId;

/**
 * Parse CSPC campus ID OCR text into registration fields.
 *
 * Physical IDs label the student number as "SN: …" (e.g. SN: 231002254).
 * Names are usually two or more lines in ALL CAPS. Long names may wrap across
 * several same-size lines before the smaller address text below.
 */
class CampusIdParser
{
    private const NAME_BAND_MIN = 0.52;

    private const NAME_BAND_MAX = 0.715;

    /** @var list<string> */
    private const SKIP_LINE_PATTERNS = [
        '/\bcamarines\b/i',
        '/\bpolytechnic\b/i',
        '/\bcolleges?\b/i',
        '/\bnabua\b/i',
        '/\bdate\s*of\s*birth\b/i',
        '/\bsignature\b/i',
        '/\bzone\b/i',
        '/\biso\b/i',
        '/\btuv\b/i',
        '/\baddress\b/i',
        '/\bcourse\b/i',
        '/\bdepartment\b/i',
        '/\bvalid\b/i',
        '/\bexpir/i',
        '/\bcristo\b/i',
        '/\bbato\b/i',
        '/\brey\b/i',
        '/^bs/i',
        '/^is0\b/i',
    ];

    /**
     * @param  list<array{text: string, confidence?: float|null, height?: float|null, center_y?: float|null}>  $lines
     * @return array{
     *     id_number: ?string,
     *     full_name: ?string,
     *     name_complete: bool,
     *     warnings: list<string>
     * }
     */
    public function parse(array $lines): array
    {
        $normalized = $this->normalizeLines($lines);
        $rawText = implode("\n", array_column($normalized, 'text'));

        $idNumber = $this->extractIdNumber($normalized, $rawText);
        $nameResult = $this->extractFullName($normalized);

        $warnings = [];
        if ($idNumber === null) {
            $warnings[] = 'Could not read the SN (ID number). Please enter it manually.';
        }
        if (! $nameResult['complete']) {
            $warnings[] = 'Could not read the full name from your ID. Enter your complete name manually.';
        }

        return [
            'id_number' => $idNumber,
            'full_name' => $nameResult['complete'] ? $nameResult['value'] : null,
            'name_complete' => $nameResult['complete'],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array{text: string, confidence?: float|null, height?: float|null, center_y?: float|null}>  $lines
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) ($line['text'] ?? '')) ?? '');
            if ($text === '') {
                continue;
            }

            $centerY = isset($line['center_y']) ? (float) $line['center_y'] : null;

            $out[] = [
                'text' => $text,
                'confidence' => (float) ($line['confidence'] ?? 0.0),
                'height' => (float) ($line['height'] ?? 0.0),
                'center_y' => $centerY,
            ];
        }

        usort($out, function (array $left, array $right): int {
            $leftY = $left['center_y'] ?? 0.0;
            $rightY = $right['center_y'] ?? 0.0;

            if ($leftY !== $rightY) {
                return $leftY <=> $rightY;
            }

            return ($right['height'] ?? 0.0) <=> ($left['height'] ?? 0.0);
        });

        return $out;
    }

    /**
     * @param  list<array{text: string, confidence: float, height: float, center_y: ?float}>  $lines
     */
    private function extractIdNumber(array $lines, string $rawText): ?string
    {
        if (preg_match('/\bSN\s*[:#.]?\s*([0-9]{6,12})\b/i', $rawText, $match)) {
            return $match[1];
        }

        $best = null;
        $bestScore = -1.0;

        foreach ($lines as $line) {
            $text = $line['text'];
            $confidence = $line['confidence'];

            if (preg_match('/\bSN\b/i', $text) && preg_match('/([0-9]{6,12})/', $text, $match)) {
                $score = $confidence + 1.0;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $match[1];
                }
            }

            if (preg_match_all('/\b([0-9]{8,10})\b/', $text, $matches)) {
                foreach ($matches[1] as $digits) {
                    $digitRatio = strlen($digits) / max(1, strlen(preg_replace('/\s+/', '', $text) ?: $text));
                    $score = $confidence * $digitRatio;

                    if (strlen($digits) === 9) {
                        $score += 0.15;
                    }

                    if ($digitRatio >= 0.5 && $score > $bestScore) {
                        $bestScore = $score;
                        $best = $digits;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * @param  list<array{text: string, confidence: float, height: float, center_y: ?float}>  $lines
     * @return array{value: ?string, complete: bool}
     */
    private function extractFullName(array $lines): array
    {
        $empty = ['value' => null, 'complete' => false];

        $candidates = $this->nameCandidates($lines, true);

        if ($candidates === []) {
            $candidates = $this->nameCandidates($lines, false);
        }

        if ($candidates === []) {
            return $empty;
        }

        $nameGroup = $this->pickNameLineGroup($candidates);

        if ($nameGroup === []) {
            return $empty;
        }

        $parts = array_map(fn (array $line) => $line['text'], $nameGroup);
        $fullName = $this->titleCase(implode(' ', $parts));

        return [
            'value' => $fullName,
            'complete' => $this->isCompleteName($nameGroup, $fullName),
        ];
    }

    /**
     * @param  list<array{text: string, confidence: float, height: float, center_y: ?float}>  $nameGroup
     */
    private function isCompleteName(array $nameGroup, string $fullName): bool
    {
        if ($nameGroup === [] || trim($fullName) === '') {
            return false;
        }

        $totalWords = $this->wordCount($fullName);
        if ($totalWords < 3) {
            return false;
        }

        foreach ($nameGroup as $line) {
            if ($line['confidence'] < 0.55) {
                return false;
            }
        }

        $lineCount = count($nameGroup);

        if ($lineCount >= 2) {
            $firstLineWords = $this->wordCount($nameGroup[0]['text']);
            $lastLineWords = $this->wordCount($nameGroup[$lineCount - 1]['text']);

            return $firstLineWords >= 2 && $lastLineWords >= 1 && $totalWords >= 3;
        }

        return $totalWords >= 4;
    }

    /**
     * Join consecutive name lines that share the same printed size (long names).
     *
     * @param  list<array{text: string, confidence: float, height: float, center_y: ?float}>  $candidates
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    private function pickNameLineGroup(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $groups = [];
        $current = [$candidates[0]];

        for ($i = 1; $i < count($candidates); $i++) {
            $previous = $candidates[$i - 1];
            $line = $candidates[$i];

            if ($this->linesBelongToSameNameBlock($previous, $line)) {
                $current[] = $line;
                continue;
            }

            $groups[] = $current;
            $current = [$line];
        }

        $groups[] = $current;

        usort($groups, function (array $left, array $right): int {
            if (count($left) !== count($right)) {
                return count($right) <=> count($left);
            }

            $avgHeightLeft = array_sum(array_column($left, 'height')) / max(1, count($left));
            $avgHeightRight = array_sum(array_column($right, 'height')) / max(1, count($right));

            return $avgHeightRight <=> $avgHeightLeft;
        });

        return $groups[0];
    }

    /**
     * @param  array{text: string, confidence: float, height: float, center_y: ?float}  $previous
     * @param  array{text: string, confidence: float, height: float, center_y: ?float}  $line
     */
    private function linesBelongToSameNameBlock(array $previous, array $line): bool
    {
        if (! $this->heightsAreSimilar($previous['height'], $line['height'])) {
            return false;
        }

        $previousY = $previous['center_y'];
        $lineY = $line['center_y'];

        if ($previousY === null || $lineY === null) {
            return true;
        }

        return ($lineY - $previousY) <= 0.09;
    }

    private function heightsAreSimilar(float $left, float $right): bool
    {
        if ($left <= 0.0 || $right <= 0.0) {
            return true;
        }

        $smaller = min($left, $right);
        $larger = max($left, $right);

        return ($smaller / $larger) >= 0.72;
    }

    /**
     * @param  list<array{text: string, confidence: float, height: float, center_y: ?float}>  $lines
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    private function nameCandidates(array $lines, bool $restrictToNameBand): array
    {
        $candidates = [];

        foreach ($lines as $line) {
            $text = trim($line['text']);
            if (! $this->looksLikeNameLine($text)) {
                continue;
            }

            $centerY = $line['center_y'];
            if ($restrictToNameBand && $centerY !== null && ($centerY < self::NAME_BAND_MIN || $centerY > self::NAME_BAND_MAX)) {
                continue;
            }

            $candidates[] = $line;
        }

        return $candidates;
    }

    private function looksLikeNameLine(string $text): bool
    {
        if (! preg_match('/^[A-Za-z][A-Za-z\s\'.-]+$/u', $text)) {
            return false;
        }

        if (str_contains($text, ',') || str_contains($text, '#')) {
            return false;
        }

        if (strlen($text) < 3 || $this->wordCount($text) < 1) {
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

        $upperRatio = $this->uppercaseLetterRatio($text);

        return $upperRatio >= 0.7;
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, fn ($word) => $word !== ''));
    }

    private function uppercaseLetterRatio(string $text): float
    {
        $letters = preg_replace('/[^A-Za-z]/u', '', $text) ?? '';

        if ($letters === '') {
            return 0.0;
        }

        $upper = preg_replace('/[^A-Z]/u', '', $letters) ?? '';

        return strlen($upper) / strlen($letters);
    }

    private function titleCase(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
