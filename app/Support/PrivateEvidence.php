<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve violation evidence from the private disk (legacy public copies still readable).
 */
class PrivateEvidence
{
    public static function isSafePath(?string $path): bool
    {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return str_starts_with($path, 'violation-evidence/');
    }

    public static function response(?string $path): StreamedResponse|\Illuminate\Http\Response
    {
        $path = ViolationEvidence::normalizePath((string) $path);

        if (! self::isSafePath($path)) {
            abort(404);
        }

        $absolute = ViolationEvidence::absolutePath($path);
        if ($absolute === null || ! is_file($absolute)) {
            abort(404);
        }

        $mime = @mime_content_type($absolute) ?: 'application/octet-stream';
        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
