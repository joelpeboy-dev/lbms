<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'weather' => [
        'key' => env('WEATHER_API_KEY'),
        'provider' => env('WEATHER_PROVIDER', 'open-meteo'),
        'endpoint' => env('WEATHER_API_ENDPOINT', 'https://api.open-meteo.com/v1/forecast'),
        'geocoding_endpoint' => env('WEATHER_GEOCODING_ENDPOINT', 'https://geocoding-api.open-meteo.com/v1/search'),
        'city' => env('WEATHER_CITY', 'Manila'),
        'latitude' => env('WEATHER_LATITUDE', '14.5995'),
        'longitude' => env('WEATHER_LONGITUDE', '120.9842'),
        'timeout' => env('WEATHER_API_TIMEOUT', 15),
        'cities' => [
            'Manila',
            'Cebu City',
            'Davao City',
            'Quezon City',
            'Baguio',
            'Iloilo City',
            'Bacolod',
            'Cagayan de Oro',
            'Tagaytay',
            'Tokyo',
            'Singapore',
            'Seoul',
            'New York',
            'London',
            'Sydney',
        ],
    ],

    'study_helper' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'endpoint' => env('OPENAI_CHAT_COMPLETIONS_URL', 'https://api.openai.com/v1/chat/completions'),
        'timeout' => env('OPENAI_TIMEOUT', 45),
    ],

    'internal_api' => [
        'key' => env('INTERNAL_API_KEY'),
        'user_email' => env('INTERNAL_API_USER_EMAIL'),
    ],

];
