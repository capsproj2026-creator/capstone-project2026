<?php

namespace App\Support;

use App\Models\ViolationLog;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve violation evidence paths and public/authenticated image URLs.
 */
class ViolationEvidence
{
    public const DISK = 'public';

    public const DIRECTORY = 'violation-evidence';

    /**
     * @return list<string>
     */
    public static function pathsFor(ViolationLog $log): array
    {
        $photos = $log->evidence_photos ?? null;

        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = is_array($decoded) ? $decoded : null;
        }

        if (is_array($photos)) {
            $paths = array_values(array_filter(
                $photos,
                fn ($path) => is_string($path) && trim($path) !== ''
            ));

            if ($paths !== []) {
                return array_map(fn ($path) => self::normalizePath($path), $paths);
            }
        }

        $single = trim((string) ($log->evidence_photo ?? ''));

        return $single !== '' ? [self::normalizePath($single)] : [];
    }

    public static function findAuthorized(string|int $id): ViolationLog
    {
        $log = ViolationLog::query()->find((int) $id)
            ?? ViolationLog::query()->find($id);

        if (! $log) {
            abort(404);
        }

        return $log;
    }

    public static function hasEvidence(ViolationLog $log): bool
    {
        return self::pathsFor($log) !== [];
    }

    /**
     * Prefer public Storage::url() when the file exists on the public disk.
     * Fall back to an authorized route URL for legacy private-disk files.
     *
     * @return list<string>
     */
    public static function urlsFor(ViolationLog $log, string $routeName): array
    {
        $id = (string) $log->getKey();
        $urls = [];

        foreach (self::pathsFor($log) as $index => $path) {
            self::ensurePublicCopy($path);

            if (Storage::disk(self::DISK)->exists($path)) {
                $urls[] = Storage::disk(self::DISK)->url($path);
                continue;
            }

            $urls[] = route($routeName, ['id' => $id, 'index' => $index]);
        }

        return $urls;
    }

    public static function pathAt(ViolationLog $log, int $index = 0): ?string
    {
        return self::pathsFor($log)[$index] ?? null;
    }

    /**
     * Absolute filesystem path for mail attachments, if the file exists.
     */
    public static function absolutePath(string $path): ?string
    {
        $path = self::normalizePath($path);
        self::ensurePublicCopy($path);

        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->path($path);
        }

        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->path($path);
        }

        return null;
    }

    public static function publicUrl(string $path): ?string
    {
        $path = self::normalizePath($path);
        self::ensurePublicCopy($path);

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path;
    }

    /**
     * Copy legacy private-disk evidence into the public disk once so Storage::url works.
     */
    public static function ensurePublicCopy(string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === '' || Storage::disk(self::DISK)->exists($path)) {
            return;
        }

        if (! Storage::disk('private')->exists($path)) {
            return;
        }

        try {
            Storage::disk(self::DISK)->put($path, Storage::disk('private')->get($path));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
