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

    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_ssl' => (bool) env('TURNSTILE_VERIFY_SSL', true),
    ],

    'ai' => [
        'primary' => (string) env('AI_PRIMARY', 'gemini'),
        'fallback' => (string) env('AI_FALLBACK', 'ollama'),
        'timeout_ms' => (int) env('AI_TIMEOUT_MS', 15000),
        'retry_count' => (int) env('AI_RETRY_COUNT', 1),
        'masking_enabled' => (bool) env('AI_MASKING_ENABLED', true),
        'verify_ssl' => (bool) env('AI_VERIFY_SSL', true),
        'artifact_max_chars' => (int) env('AI_ARTIFACT_MAX_CHARS', 12000),
        'pdf_extraction_enabled' => (bool) env('AI_PDF_EXTRACTION_ENABLED', true),
        'preprocess_with_ollama' => (bool) env('AI_PREPROCESS_WITH_OLLAMA', false),
        'auto_apply_min_confidence' => (int) env('AI_AUTO_APPLY_MIN_CONFIDENCE', 65),
        'qa_detector' => [
            'use_ai' => (bool) env('AI_QA_DETECTOR_USE_AI', true),
            'max_chars' => (int) env('AI_QA_DETECTOR_MAX_CHARS', 12000),
            'merge_into_advisor_prompt' => (bool) env('AI_QA_DETECTOR_MERGE_INTO_ADVISOR', true),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => (string) env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
            'base_url' => (string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
        ],

        'ollama' => [
            'base_url' => (string) env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => (string) env('OLLAMA_MODEL', 'qwen3.5:4b'),
        ],
    ],

];
