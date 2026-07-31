<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rfid' => [
        'api_token' => env('RFID_API_TOKEN'),
    ],

    'ai_parking' => [
        'api_token' => env('AI_PARKING_API_TOKEN'),
        'stream_url' => env('AI_PARKING_STREAM_URL', 'http://127.0.0.1:8090/stream.mjpg'),
        // Direct MJPEG URL for <img> tags (bypasses Laravel; required for php artisan serve)
        'stream_browser_url' => env('AI_PARKING_STREAM_BROWSER_URL'),
        'area_id' => (int) env('AI_PARKING_AREA_ID', 19),
        'camera_ip' => env('AI_CAMERA_IP', '192.168.1.108'),
        'overtime_minutes' => (int) env('AI_PARKING_OVERTIME_MINUTES', 30),
        'violation_debounce_minutes' => (int) env('AI_PARKING_VIOLATION_DEBOUNCE_MINUTES', 10),
        'ingest_stale_seconds' => (int) env('AI_PARKING_INGEST_STALE_SECONDS', 45),
    ],

];
