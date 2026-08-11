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
        'fallback' => (string) env('AI_FALLBACK', 'none'),
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
        'extraction' => [
            'python_binary' => (string) env('AI_EXTRACTION_PYTHON_BINARY', 'python'),
            'script_path' => (string) env('AI_EXTRACTION_SCRIPT_PATH', 'scripts/submission_extractor.py'),
            'max_chars' => (int) env('AI_EXTRACTION_MAX_CHARS', 200000),
            'ocr_timeout_seconds' => (int) env('AI_EXTRACTION_OCR_TIMEOUT_SECONDS', 60),
            'tesseract_binary' => (string) env('AI_EXTRACTION_TESSERACT_BINARY', 'tesseract'),
            'pdftoppm_binary' => (string) env('AI_EXTRACTION_PDFTOPPM_BINARY', 'pdftoppm'),
            'tesseract_lang' => (string) env('AI_EXTRACTION_TESSERACT_LANG', 'ind+eng'),
        ],
        'cleaning' => [
            'python_binary' => (string) env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python')),
            'script_path' => (string) env('AI_CLEANING_SCRIPT_PATH', 'scripts/submission_cleaner.py'),
            'max_chars' => (int) env('AI_CLEANING_MAX_CHARS', 200000),
            'timeout_seconds' => (int) env('AI_CLEANING_TIMEOUT_SECONDS', 30),
        ],
        'structure_detection' => [
            'python_binary' => (string) env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python'))),
            'script_path' => (string) env('AI_STRUCTURE_SCRIPT_PATH', 'scripts/submission_structure_detector.py'),
            'max_chars' => (int) env('AI_STRUCTURE_MAX_CHARS', 200000),
            'timeout_seconds' => (int) env('AI_STRUCTURE_TIMEOUT_SECONDS', 30),
        ],
        'semantic_enrichment' => [
            'python_binary' => (string) env('AI_SEMANTIC_PYTHON_BINARY', env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python')))),
            'script_path' => (string) env('AI_SEMANTIC_SCRIPT_PATH', 'scripts/submission_semantic_enricher.py'),
            'max_items' => (int) env('AI_SEMANTIC_MAX_ITEMS', 500),
            'timeout_seconds' => (int) env('AI_SEMANTIC_TIMEOUT_SECONDS', 30),
        ],
        'rubric_preparation' => [
            'python_binary' => (string) env('AI_RUBRIC_PYTHON_BINARY', env('AI_SEMANTIC_PYTHON_BINARY', env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python'))))),
            'script_path' => (string) env('AI_RUBRIC_SCRIPT_PATH', 'scripts/submission_rubric_preparer.py'),
            'max_items' => (int) env('AI_RUBRIC_MAX_ITEMS', 500),
            'allowed_feedback_length' => (int) env('AI_RUBRIC_ALLOWED_FEEDBACK_LENGTH', 200),
            'timeout_seconds' => (int) env('AI_RUBRIC_TIMEOUT_SECONDS', 30),
        ],
        'ai_evaluation' => [
            'python_binary' => (string) env('AI_EVALUATION_PYTHON_BINARY', env('AI_RUBRIC_PYTHON_BINARY', env('AI_SEMANTIC_PYTHON_BINARY', env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python')))))),
            'script_path' => (string) env('AI_EVALUATION_SCRIPT_PATH', 'scripts/submission_ai_evaluator.py'),
            'max_items' => (int) env('AI_EVALUATION_MAX_ITEMS', 500),
            'timeout_seconds' => (int) env('AI_EVALUATION_TIMEOUT_SECONDS', 30),
        ],
        'post_evaluation_validation' => [
            'python_binary' => (string) env('AI_POST_EVAL_PYTHON_BINARY', env('AI_EVALUATION_PYTHON_BINARY', env('AI_RUBRIC_PYTHON_BINARY', env('AI_SEMANTIC_PYTHON_BINARY', env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python'))))))),
            'script_path' => (string) env('AI_POST_EVAL_SCRIPT_PATH', 'scripts/submission_post_evaluation_validator.py'),
            'max_items' => (int) env('AI_POST_EVAL_MAX_ITEMS', 500),
            'timeout_seconds' => (int) env('AI_POST_EVAL_TIMEOUT_SECONDS', 30),
            'max_retries' => (int) env('AI_POST_EVAL_MAX_RETRIES', 2),
        ],
        'result_presentation' => [
            'python_binary' => (string) env('AI_RESULT_PRES_PYTHON_BINARY', env('AI_POST_EVAL_PYTHON_BINARY', env('AI_EVALUATION_PYTHON_BINARY', env('AI_RUBRIC_PYTHON_BINARY', env('AI_SEMANTIC_PYTHON_BINARY', env('AI_STRUCTURE_PYTHON_BINARY', env('AI_CLEANING_PYTHON_BINARY', env('AI_EXTRACTION_PYTHON_BINARY', 'python')))))))),
            'script_path' => (string) env('AI_RESULT_PRES_SCRIPT_PATH', 'scripts/submission_result_presenter.py'),
            'max_items' => (int) env('AI_RESULT_PRES_MAX_ITEMS', 500),
            'timeout_seconds' => (int) env('AI_RESULT_PRES_TIMEOUT_SECONDS', 30),
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
