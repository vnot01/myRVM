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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
        'redirect' => env('GOOGLE_REDIRECT_URI_WEB'),



        'api_key' => env('GOOGLE_API_KEY'),
        'api_endpoint_flash' => env('GEMINI_API_ENDPOINT_FLASH'),
        'api_endpoint_pro' => env('GEMINI_API_ENDPOINT_PRO'),
        'api_endpoint_2_5_flash_preview' => env('GEMINI_API_ENDPOINT_2_5_flash_preview'),
        'api_endpoint_2_5_pro_preview' => env('GEMINI_API_ENDPOINT_2_5_pro_preview'),
        'api_endpoint_pro_latest' => env('GEMINI_API_ENDPOINT_PRO_LATEST'),
        'api_endpoint_flash_latest' => env('GEMINI_API_ENDPOINT_FLASH_LATEST'),
        
        
    ],

];
