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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8000').'/auth/google/callback'),
        // Empty = allow any Google email. Default campus domain for CSPC.
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN', 'my.cspc.edu.ph'),
    ],

    'visitor_pre_register' => [
        // When set, QR codes and /visitor/pre-register redirect here instead of the built-in form.
        'google_form_url' => env('VISITOR_PRE_REGISTER_GOOGLE_FORM_URL'),
        // Shared secret for Google Apps Script → POST /api/visitor/pre-register/google
        'webhook_token' => env('VISITOR_PRE_REGISTER_WEBHOOK_TOKEN'),
        // After campus entry, the same form may still be submitted for this many hours.
        'post_entry_hours' => max(1, (int) env('VISITOR_POST_ENTRY_HOURS', 5)),
    ],

    'rfid' => [
        'api_token' => env('RFID_API_TOKEN'),
        // One physical boom/servo wired to this ESP32 (usually Entry). Exit grants open it via heartbeat.
        'shared_boom_gate_id' => strtoupper(trim((string) env('RFID_SHARED_BOOM_GATE_ID', 'GATE-IN-1'))),
        // 1 = Exit RFID can grant (and open Entry servo) even if there is no prior Entry log (hardware demo).
        'allow_exit_without_entry' => filter_var(env('RFID_ALLOW_EXIT_WITHOUT_ENTRY', false), FILTER_VALIDATE_BOOLEAN),
        // Unknown RFID at Entry: unregistered student/faculty get a one-time gate pass
        // until they complete vehicle registration (visitors use VisitorRfidCard).
        'temp_access_enabled' => filter_var(env('RFID_TEMP_ACCESS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'temp_access_hours' => max(1, (int) env('RFID_TEMP_ACCESS_HOURS', 5)),
        'temp_access_max' => max(1, (int) env('RFID_TEMP_ACCESS_MAX', 3)),
    ],

    'registration' => [
        'remedial_gate_enabled' => filter_var(env('REGISTRATION_REMEDIAL_GATE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'remedial_hours' => max(0, (int) env('REGISTRATION_REMEDIAL_HOURS', 5)),
        'remedial_one_entry' => filter_var(env('REGISTRATION_REMEDIAL_ONE_ENTRY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'ai_parking' => [
        'api_token' => env('AI_PARKING_API_TOKEN'),
        // Legacy single-stream keys (CAM-AI-1 / primary)
        'stream_url' => env('AI_PARKING_STREAM_URL', 'http://127.0.0.1:8090/stream.mjpg'),
        'stream_browser_url' => env('AI_PARKING_STREAM_BROWSER_URL'),
        'stream_base' => env('AI_PARKING_STREAM_BASE', 'http://127.0.0.1:8090'),
        // ACAD 1 (CapstoneSeeder id 4). Do not fall back to removed AI test lots 19–21.
        'area_id' => (int) env('AI_PARKING_AREA_ID', env('AI_CAMERA_1_AREA_ID', 4)),
        'camera_ip' => env('AI_CAMERA_IP', env('AI_CAMERA_1_IP')),
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
                'name' => env('AI_CAMERA_1_NAME', 'ACAD 1 Building (Front)'),
                'location' => env('AI_CAMERA_1_LOCATION', 'ACAD 1 Building (Front)'),
                'area_id' => (int) env('AI_CAMERA_1_AREA_ID', env('AI_PARKING_AREA_ID', 4)),
                'stream_path' => env('AI_CAMERA_1_STREAM_PATH', '/stream.mjpg'),
                'stream_url' => env('AI_CAMERA_1_STREAM_URL', env('AI_PARKING_STREAM_BROWSER_URL', env('AI_PARKING_STREAM_URL'))),
                'ai_stream_path' => env('AI_CAMERA_1_AI_STREAM_PATH'),
                'ai_stream_url' => env('AI_CAMERA_1_AI_STREAM_URL'),
                'enabled' => filter_var(env('AI_CAMERA_1_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            ],
            [
                'id' => env('AI_CAMERA_2_ID', 'CAM-AI-2'),
                'name' => env('AI_CAMERA_2_NAME', 'Campus Camera 2'),
                'location' => env('AI_CAMERA_2_LOCATION', 'Parking Lot B'),
                'area_id' => (int) env('AI_CAMERA_2_AREA_ID', 15),
                'stream_path' => env('AI_CAMERA_2_STREAM_PATH'),
                'stream_url' => env('AI_CAMERA_2_STREAM_URL'),
                'ai_stream_path' => env('AI_CAMERA_2_AI_STREAM_PATH'),
                'ai_stream_url' => env('AI_CAMERA_2_AI_STREAM_URL'),
                'enabled' => filter_var(env('AI_CAMERA_2_ENABLED', true), FILTER_VALIDATE_BOOLEAN)
                    && filled(env('AI_CAMERA_2_IP')),
            ],
            [
                'id' => env('AI_CAMERA_3_ID', 'CAM-AI-3'),
                'name' => env('AI_CAMERA_3_NAME', 'Campus Camera 3'),
                'location' => env('AI_CAMERA_3_LOCATION', 'Visitor Parking'),
                'area_id' => (int) env('AI_CAMERA_3_AREA_ID', 9),
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

    'campus_id' => [
        'ocr_python' => env('CAMPUS_ID_OCR_PYTHON'),
    ],

];
