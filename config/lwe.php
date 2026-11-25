<?php

return [
    // Times are stored in UTC and shown to people in this timezone.
    'timezone' => env('LWE_TIMEZONE', 'Asia/Karachi'),

    // WaFlow Hub (our WhatsApp tool). Everything about its API is set in .env (see below).
    'waflow' => [
        'base_url' => env('WAFLOW_BASE_URL'),          // e.g. https://hub.learningwithearning.org
        'token' => env('WAFLOW_API_TOKEN'),
        'send_path' => env('WAFLOW_SEND_PATH', '/api/messages/send'),
        'enabled' => env('WAFLOW_ENABLED', false),     // false = messages are only written to the log
        // Match these to WaFlow Hub's "send message" API — no code change needed:
        'auth' => env('WAFLOW_AUTH', 'bearer'),         // bearer | header:X-API-KEY | query:api_key | body:token
        'phone_field' => env('WAFLOW_PHONE_FIELD', 'phone'),
        'message_field' => env('WAFLOW_MESSAGE_FIELD', 'message'),
        'phone_format' => env('WAFLOW_PHONE_FORMAT', '92'),   // 92 → 923001234567 · +92 → +923001234567 · 0 → 03001234567
        'extra' => env('WAFLOW_EXTRA', ''),                  // extra JSON fields, e.g. {"instance_id":"abc","type":"text"}
        'as_form' => env('WAFLOW_AS_FORM', false),           // true = send as form fields instead of JSON
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
    ],

    // AI (Anthropic Claude API). The API key stays in .env only.
    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2026-10-21'),
        'model' => env('AI_MODEL', 'claude-haiku-5-5'),          // fast + low cost, reads images too
        'timeout' => (int) env('AI_TIMEOUT', 60),
    ],

    'uploads' => [
        'max_image_kb' => 4096,
        'max_attachment_kb' => 20480,
    ],
];
