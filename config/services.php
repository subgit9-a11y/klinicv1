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

    'cashfree' => [
        'app_id' => env('CASHFREE_APP_ID'),
        'secret_key' => env('CASHFREE_SECRET_KEY'),
        'base_url' => env('CASHFREE_BASE_URL', 'https://api.cashfree.com/pg'),
    ],

    'google' => [
        'meet_client_id' => env('GOOGLE_MEET_CLIENT_ID'),
        'meet_client_secret' => env('GOOGLE_MEET_CLIENT_SECRET'),
        'meet_refresh_token' => env('GOOGLE_MEET_REFRESH_TOKEN'),
    ],

    'whatsapp' => [
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
    ],

    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'sender_id' => env('MSG91_SENDER_ID', 'KLINIC'),
        'route' => env('MSG91_ROUTE', '4'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY') ?? env('POSTMARK_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    // Which OCR/Speech implementation is active (resolved in AppServiceProvider).
    'ocr_provider' => env('OCR_PROVIDER', 'google_vision'),
    'speech_provider' => env('SPEECH_PROVIDER', 'openai'),

    // OCR providers (one active at a time, resolved by OCR_PROVIDER env).
    'google_vision' => [
        'api_key' => env('GOOGLE_VISION_API_KEY'),
        'base_url' => env('GOOGLE_VISION_BASE_URL', 'https://vision.googleapis.com/v1'),
    ],
    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', 'tesseract'),
        'lang' => env('TESSERACT_LANG', 'eng'),
    ],

    // Speech-to-text providers (one active at a time, resolved by SPEECH_PROVIDER env).
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],
    'whisper_cpp' => [
        'binary' => env('WHISPER_CPP_BINARY', 'whisper-cli'),
        'model' => env('WHISPER_CPP_MODEL'), // path to ggml-*.bin model file
    ],

];
