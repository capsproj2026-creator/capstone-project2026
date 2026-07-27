<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SafeUpload
{
    /**
     * Store an upload. Use disk "local" for PII documents; "public" only for non-sensitive assets (avatars).
     */
    public static function store(UploadedFile $file, string $directory, string $prefix, string $disk = 'local'): string
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'bin';
        $filename = time().'_'.$prefix.'_'.Str::random(16).'.'.$extension;

        $file->storeAs($directory, $filename, $disk);

        return $filename;
    }
}
