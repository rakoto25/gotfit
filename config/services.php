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
        'connect_country' => env('STRIPE_CONNECT_COUNTRY', 'FR'),
        'validation_delay_hours' => env('STRIPE_VALIDATION_DELAY_HOURS', 72),
        'pack_validation_delay_hours' => env('STRIPE_PACK_VALIDATION_DELAY_HOURS', 48),
        'frontend_url' => env('FRONTEND_URL', 'https://gotfit.tech'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'contact' => [
        'to' => env('CONTACT_MAIL_TO', env('MAIL_FROM_ADDRESS')),
    ],

    'visio' => [
        'provider' => env('VISIO_PROVIDER', 'livekit'),
        'server_url' => env('VISIO_SERVER_URL'),
        'api_key' => env('VISIO_API_KEY'),
        'api_secret' => env('VISIO_API_SECRET'),
        'secret' => env('VISIO_SECRET'),
        'token_ttl' => env('VISIO_TOKEN_TTL', 3600),
        'frontend_url' => env('FRONTEND_URL', 'https://gotfit.tech'),
    ],

];
