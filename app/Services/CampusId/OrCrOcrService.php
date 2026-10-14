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
     *     owner_name: ?string,
     *     has_lto: bool,
     *     has_document_keyword: bool,
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
                'ok' => false,
                'kind' => $kind,
                'plate_number' => null,
                'owner_name' => null,
                'has_lto' => false,
                'has_document_keyword' => false,
                'warnings' => ['Upload a clear photo of the LTO document. PDF files cannot be checked.'],
                'message' => 'Upload a clear photo of the LTO document.',
            ];
        }

        $lines = $this->ocr->extractLines($file, true);
        $parsed = $this->parser->parse($lines, $kind, $expectedPlate);
        $warnings = $parsed['warnings'];

        return [
            'ok' => true,
            'kind' => $kind,
            'plate_number' => $parsed['plate_number'],
            'owner_name' => $parsed['owner_name'] ?? null,
            'vehicle_model' => $parsed['vehicle_model'] ?? null,
            'vehicle_color' => $parsed['vehicle_color'] ?? null,
            'has_lto' => (bool) ($parsed['has_lto'] ?? false),
            'has_document_keyword' => (bool) ($parsed['has_document_keyword'] ?? false),
            'warnings' => $warnings,
            'message' => $warnings === []
                ? 'Document keywords look consistent with an LTO file. Please still review before submitting.'
                : 'Please review this document. OCR is assistive and may misread text.',
        ];
    }
}
