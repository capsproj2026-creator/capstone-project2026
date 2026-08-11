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
        // Legacy single-stream keys (CAM-AI-1 / primary)
        'stream_url' => env('AI_PARKING_STREAM_URL', 'http://127.0.0.1:8090/stream.mjpg'),
        'stream_browser_url' => env('AI_PARKING_STREAM_BROWSER_URL'),
        'stream_base' => env('AI_PARKING_STREAM_BASE', 'http://127.0.0.1:8090'),
        'area_id' => (int) env('AI_PARKING_AREA_ID', env('AI_CAMERA_1_AREA_ID', 19)),
        'camera_ip' => env('AI_CAMERA_IP', env('AI_CAMERA_1_IP', '192.168.1.108')),
        'overtime_minutes' => (int) env('AI_PARKING_OVERTIME_MINUTES', 30),
        'violation_debounce_minutes' => (int) env('AI_PARKING_VIOLATION_DEBOUNCE_MINUTES', 10),
        'ingest_stale_seconds' => (int) env('AI_PARKING_INGEST_STALE_SECONDS', 45),

        /*
        | Multi-camera registry (add AI_CAMERA_N_* in .env to extend).
        | RTSP credentials stay in .env for the Python service only — Laravel
        | only needs public stream URLs + area mapping.
        */
        'cameras' => array_values(array_filter([
            [
                'id' => env('AI_CAMERA_1_ID', env('AI_CAMERA_ID', 'CAM-AI-1')),
                'name' => env('AI_CAMERA_1_NAME', 'AI Test Lot'),
                'location' => env('AI_CAMERA_1_LOCATION', 'Parking Lot A'),
                'area_id' => (int) env('AI_CAMERA_1_AREA_ID', env('AI_PARKING_AREA_ID', 19)),
                'stream_path' => env('AI_CAMERA_1_STREAM_PATH', '/stream.mjpg'),
                'stream_url' => env('AI_CAMERA_1_STREAM_URL', env('AI_PARKING_STREAM_BROWSER_URL', env('AI_PARKING_STREAM_URL'))),
                'ai_stream_path' => env('AI_CAMERA_1_AI_STREAM_PATH'),
                'ai_stream_url' => env('AI_CAMERA_1_AI_STREAM_URL'),
                'enabled' => filter_var(env('AI_CAMERA_1_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            ],
            [
                'id' => env('AI_CAMERA_2_ID', 'CAM-AI-2'),
                'name' => env('AI_CAMERA_2_NAME', 'AI Lot B'),
                'location' => env('AI_CAMERA_2_LOCATION', 'Parking Lot B'),
                'area_id' => (int) env('AI_CAMERA_2_AREA_ID', 20),
                'stream_path' => env('AI_CAMERA_2_STREAM_PATH'),
                'stream_url' => env('AI_CAMERA_2_STREAM_URL'),
                'ai_stream_path' => env('AI_CAMERA_2_AI_STREAM_PATH'),
                'ai_stream_url' => env('AI_CAMERA_2_AI_STREAM_URL'),
                'enabled' => filter_var(env('AI_CAMERA_2_ENABLED', true), FILTER_VALIDATE_BOOLEAN)
                    && filled(env('AI_CAMERA_2_IP')),
            ],
            [
                'id' => env('AI_CAMERA_3_ID', 'CAM-AI-3'),
                'name' => env('AI_CAMERA_3_NAME', 'AI Lot C'),
                'location' => env('AI_CAMERA_3_LOCATION', 'Visitor Parking'),
                'area_id' => (int) env('AI_CAMERA_3_AREA_ID', 21),
                'stream_path' => env('AI_CAMERA_3_STREAM_PATH'),
                'stream_url' => env('AI_CAMERA_3_STREAM_URL'),
                'ai_stream_path' => env('AI_CAMERA_3_AI_STREAM_PATH'),
                'ai_stream_url' => env('AI_CAMERA_3_AI_STREAM_URL'),
                // Keep template in .env; only activate when ENABLED=true and IP is set.
                'enabled' => filter_var(env('AI_CAMERA_3_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
                    && filled(env('AI_CAMERA_3_IP')),
            ],
        ])),
    ],

];
