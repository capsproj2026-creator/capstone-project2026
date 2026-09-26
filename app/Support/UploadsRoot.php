<?php

namespace App\Support;

/**
 * Resolve the on-disk root for user uploads (images/PDFs).
 *
 * When ISCVMS_UPLOADS_ROOT is set (absolute or relative to the project),
 * uploads live outside storage/app so the app tree stays lean while Laravel
 * still reads/writes via the local/private/public disks.
 */
class UploadsRoot
{
    /**
     * Absolute path that contains private/ and public/ upload trees.
     */
    public static function path(): string
    {
        $raw = trim((string) env('ISCVMS_UPLOADS_ROOT', ''));
        if ($raw === '') {
            return storage_path('app');
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);

        if (preg_match('#^[A-Za-z]:\\\\#', $normalized)
            || str_starts_with($normalized, DIRECTORY_SEPARATOR)
            || str_starts_with($normalized, '\\\\')) {
            return rtrim($normalized, DIRECTORY_SEPARATOR);
        }

        return rtrim(base_path($normalized), DIRECTORY_SEPARATOR);
    }

    public static function privatePath(): string
    {
        return self::path().DIRECTORY_SEPARATOR.'private';
    }

    public static function publicPath(): string
    {
        return self::path().DIRECTORY_SEPARATOR.'public';
    }

    public static function isExternal(): bool
    {
        return trim((string) env('ISCVMS_UPLOADS_ROOT', '')) !== '';
    }

    /**
     * Create private/public trees (and common subfolders) if missing.
     */
    public static function ensureDirectories(): void
    {
        $dirs = [
            self::privatePath(),
            self::publicPath(),
            self::privatePath().DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'license',
            self::privatePath().DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'orcr',
            self::privatePath().DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'cr',
            self::privatePath().DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'id',
            self::privatePath().DIRECTORY_SEPARATOR.'violation-evidence',
            self::publicPath().DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'profile',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }
}
