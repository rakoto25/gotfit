<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'validation_delay_hours' => env('STRIPE_VALIDATION_DELAY_HOURS', 72),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'visio' => [
        'provider' => env('VISIO_PROVIDER', 'livekit'),
        'server_url' => env('VISIO_SERVER_URL'),
        'api_key' => env('VISIO_API_KEY'),
        'api_secret' => env('VISIO_API_SECRET'),
        'secret' => env('VISIO_SECRET'),
        'token_ttl' => env('VISIO_TOKEN_TTL', 3600),
    ],

];
