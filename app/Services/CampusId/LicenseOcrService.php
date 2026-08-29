<?php

namespace App\Services\CampusId;

use Illuminate\Http\UploadedFile;

class LicenseOcrService
{
    public function __construct(
        private readonly CampusIdOcrService $ocr,
        private readonly LicenseParser $parser,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     full_name: ?string,
     *     address: ?string,
     *     phone_number: ?string,
     *     driver_license_number: ?string,
     *     plate_number: ?string,
     *     warnings: list<string>,
     *     message?: string
     * }
     */
    public function scan(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());

        if ($mime === 'application/pdf') {
            return $this->emptyResult('Auto-scan works best with a photo. Please upload a JPG or PNG, or enter details manually.');
        }

        if (! str_starts_with($mime, 'image/')) {
            return $this->emptyResult('Unsupported file type for scanning.');
        }

        $lines = $this->ocr->extractLines($file, true);
        $parsed = $this->parser->parse($lines);

        $hasAny = $parsed['full_name'] !== null
            || $parsed['address'] !== null
            || $parsed['phone_number'] !== null
            || $parsed['driver_license_number'] !== null
            || $parsed['plate_number'] !== null;

        return [
            'ok' => $hasAny,
            'full_name' => $parsed['full_name'],
            'address' => $parsed['address'],
            'phone_number' => $parsed['phone_number'],
            'driver_license_number' => $parsed['driver_license_number'],
            'plate_number' => $parsed['plate_number'],
            'warnings' => $parsed['warnings'],
            'message' => $hasAny
                ? null
                : 'Could not read this ID. Try a clearer, well-lit photo with the full card visible.',
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     full_name: null,
     *     address: null,
     *     phone_number: null,
     *     driver_license_number: null,
     *     plate_number: null,
     *     warnings: list<string>,
     *     message: string
     * }
     */
    private function emptyResult(string $message): array
    {
        return [
            'ok' => false,
            'full_name' => null,
            'address' => null,
            'phone_number' => null,
            'driver_license_number' => null,
            'plate_number' => null,
            'warnings' => [],
            'message' => $message,
        ];
    }
}
