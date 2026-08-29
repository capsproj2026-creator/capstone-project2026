<?php

namespace App\Services\CampusId;

use Illuminate\Http\UploadedFile;

class OrCrOcrService
{
    public function __construct(
        private readonly CampusIdOcrService $ocr,
        private readonly OrCrDocumentParser $parser,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     kind: string,
     *     plate_number: ?string,
     *     warnings: list<string>,
     *     message: string
     * }
     */
    public function scan(UploadedFile $file, string $kind, ?string $expectedPlate = null): array
    {
        $kind = strtolower(trim($kind)) === 'cr' ? 'cr' : 'or';
        $mime = strtolower((string) $file->getMimeType());

        if ($mime === 'application/pdf' || ! str_starts_with($mime, 'image/')) {
            return [
                'ok' => true,
                'kind' => $kind,
                'plate_number' => null,
                'warnings' => ['Auto-check works best with a photo. Review this file before submitting.'],
                'message' => 'Review this file before submitting.',
            ];
        }

        $lines = $this->ocr->extractLines($file, true);
        $parsed = $this->parser->parse($lines, $kind, $expectedPlate);
        $warnings = $parsed['warnings'];

        return [
            'ok' => true,
            'kind' => $kind,
            'plate_number' => $parsed['plate_number'],
            'warnings' => $warnings,
            'message' => $warnings === []
                ? 'Document keywords look consistent with an LTO file. Please still review before submitting.'
                : 'Please review this document. OCR is assistive and may misread text.',
        ];
    }
}
