<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve violation evidence from public or legacy private disk with path allowlisting.
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

        ViolationEvidence::ensurePublicCopy($path);

        $disk = null;
        if (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        } elseif (Storage::disk('private')->exists($path)) {
            $disk = 'private';
        }

        if ($disk === null) {
            abort(404);
        }

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
