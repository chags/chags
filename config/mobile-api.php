<?php

return [
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'fake'),
        'code_ttl_seconds' => (int) env('WHATSAPP_CODE_TTL_SECONDS', 300),
        'max_attempts' => (int) env('WHATSAPP_CODE_MAX_ATTEMPTS', 5),
        'resend_seconds' => (int) env('WHATSAPP_RESEND_SECONDS', 60),
    ],
    'device' => [
        'attestation_driver' => env('DEVICE_ATTESTATION_DRIVER', 'fake'),
        'challenge_ttl_seconds' => (int) env('DEVICE_CHALLENGE_TTL_SECONDS', 300),
    ],
    'faceio' => [
        'enabled' => (bool) env('FACEIO_ENABLED', false),
        'mode' => env('FACEIO_MODE', 'prototype'),
        'public_id' => env('FACEIO_PUBLIC_ID'),
        'api_key' => env('FACEIO_API_KEY'),
        'webhook_token' => env('FACEIO_WEBHOOK_TOKEN'),
    ],
];
