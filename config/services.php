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

    'xai' => [
        'api_style' => env('XAI_API_STYLE', 'chat_completions'),
        'base_url' => env('XAI_BASE_URL', 'http://jeniya.top/v1'),
        'api_key' => env('XAI_API_KEY'),
        'model' => env('XAI_MODEL', 'grok-4.2'),
        'timeout' => (int) env('XAI_TIMEOUT', 120),
        'system_prompt' => env('XAI_SYSTEM_PROMPT', 'You are a helpful AI assistant.'),
    ],

];
