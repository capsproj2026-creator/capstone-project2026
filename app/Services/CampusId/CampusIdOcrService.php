<?php

namespace App\Services\CampusId;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class CampusIdOcrService
{
    public function __construct(
        private readonly CampusIdParser $parser,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     id_number: ?string,
     *     full_name: ?string,
     *     name_complete: bool,
     *     warnings: list<string>,
     *     message?: string
     * }
     */
    public function scan(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());

        if ($mime === 'application/pdf') {
            return [
                'ok' => false,
                'id_number' => null,
                'full_name' => null,
                'name_complete' => false,
                'warnings' => [],
                'message' => 'Auto-scan works best with a photo of your ID. Please upload a JPG or PNG photo, or enter your details manually.',
            ];
        }

        if (! str_starts_with($mime, 'image/')) {
            return [
                'ok' => false,
                'id_number' => null,
                'full_name' => null,
                'name_complete' => false,
                'message' => 'Unsupported file type for scanning.',
            ];
        }

        $lines = $this->runPythonOcr($file, false);
        $parsed = $this->parser->parse($lines);

        $hasAny = $parsed['id_number'] !== null || ($parsed['full_name'] !== null && $parsed['name_complete']);

        return [
            'ok' => $hasAny,
            'id_number' => $parsed['id_number'],
            'full_name' => $parsed['full_name'],
            'name_complete' => $parsed['name_complete'],
            'warnings' => $parsed['warnings'],
            'message' => $hasAny
                ? null
                : 'Could not read your ID from this photo. Try a clearer, well-lit photo with the full card visible.',
        ];
    }

    /**
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    public function extractLines(UploadedFile $file, bool $fullCard = false): array
    {
        return $this->runPythonOcr($file, $fullCard);
    }

    /**
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    private function runPythonOcr(UploadedFile $file, bool $fullCard = false): array
    {
        $script = base_path('scripts/scan_campus_id.py');

        if (! is_file($script)) {
            throw new RuntimeException('Campus ID scan script is missing.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'cspc-id-');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to prepare temporary file for ID scan.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $imagePath = $tempPath.'.'.$extension;

        try {
            $file->move(dirname($imagePath), basename($imagePath));

            $python = CampusIdPythonResolver::binary();
            $command = array_merge(
                CampusIdPythonResolver::commandPrefix($python),
                [$script, $imagePath]
            );
            if ($fullCard) {
                $command[] = '--full';
            }

            $result = Process::timeout(180)->run($command);

            if (! $result->successful()) {
                $output = trim($result->errorOutput() ?: $result->output() ?: 'Campus ID scan failed.');
                $message = self::friendlyScanFailure($output);

                throw new RuntimeException($message);
            }

            $payload = json_decode(trim($result->output()), true);

            if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
                $message = is_array($payload) ? (string) ($payload['message'] ?? 'Campus ID scan failed.') : 'Campus ID scan failed.';

                throw new RuntimeException($message);
            }

            $legacyLines = $this->normalizeLinePayload($payload['lines'] ?? []);

            if ($fullCard) {
                return $legacyLines;
            }

            // Prefer dedicated name + SN crops so address/header text never enters name parsing.
            $nameLines = $this->normalizeLinePayload($payload['name_lines'] ?? []);
            $snLines = $this->normalizeLinePayload($payload['sn_lines'] ?? []);

            if ($nameLines !== [] || $snLines !== []) {
                return array_merge($snLines, $nameLines);
            }

            return $legacyLines;
        } finally {
            @unlink($imagePath);
            @unlink($tempPath);
        }
    }

    /**
     * @param  mixed  $lines
     * @return list<array{text: string, confidence: float, height: float, center_y: ?float}>
     */
    private function normalizeLinePayload(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'text' => $text,
                'confidence' => (float) ($line['confidence'] ?? 0.0),
                'height' => (float) ($line['height'] ?? 0.0),
                'center_y' => isset($line['center_y']) ? (float) $line['center_y'] : null,
            ];
        }

        return $normalized;
    }

    private static function friendlyScanFailure(string $output): string
    {
        if (str_contains($output, "No module named 'cv2'") || str_contains($output, 'No module named "cv2"')) {
            return 'Campus ID OCR is not set up yet. Run: powershell -ExecutionPolicy Bypass -File .\\scripts\\setup-campus-id-ocr.ps1';
        }

        if (str_contains($output, "No module named 'easyocr'") || str_contains($output, 'rapidocr')) {
            return 'Campus ID OCR is not set up yet. Run: powershell -ExecutionPolicy Bypass -File .\\scripts\\setup-campus-id-ocr.ps1';
        }

        if (str_contains($output, 'WinError 10106') || str_contains($output, '_overlapped')) {
            return 'Campus ID scan hit a Windows networking error. Restart the app, then try again. If it persists, run scripts\\setup-campus-id-ocr.ps1';
        }

        if (str_starts_with(trim($output), '{')) {
            $payload = json_decode(trim($output), true);
            if (is_array($payload) && ! empty($payload['message'])) {
                return (string) $payload['message'];
            }
        }

        return $output !== '' ? $output : 'Campus ID scan failed.';
    }
}
