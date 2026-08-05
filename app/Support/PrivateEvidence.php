<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve AI/guard violation evidence from the private disk with path allowlisting.
 */
class PrivateEvidence
{
    public static function isSafePath(?string $path): bool
    {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        return str_starts_with($path, 'violation-evidence/');
    }

    public static function response(?string $path): StreamedResponse|\Illuminate\Http\Response
    {
        if (! self::isSafePath($path) || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('private')->mimeType($path) ?: 'application/octet-stream';
        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        return Storage::disk('private')->response($path, basename((string) $path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
