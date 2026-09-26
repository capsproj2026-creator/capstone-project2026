<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | External uploads root (optional)
    |--------------------------------------------------------------------------
    |
    | When ISCVMS_UPLOADS_ROOT is set, private documents and public profile
    | photos are stored under that folder (outside the Laravel tree). Leave
    | empty to keep using storage/app (default).
    |
    | Examples:
    |   ISCVMS_UPLOADS_ROOT=C:\ISCVMS-Uploads
    |   ISCVMS_UPLOADS_ROOT=../iscvms-uploads
    |
    */

    'uploads_root' => \App\Support\UploadsRoot::path(),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => \App\Support\UploadsRoot::privatePath(),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Alias used by violation evidence uploads (non-public).
        'private' => [
            'driver' => 'local',
            'root' => \App\Support\UploadsRoot::privatePath(),
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => \App\Support\UploadsRoot::publicPath(),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | public/storage → external (or storage/app) public uploads so profile
    | photos remain reachable via /storage/...
    |
    */

    'links' => [
        public_path('storage') => \App\Support\UploadsRoot::publicPath(),
    ],

];
