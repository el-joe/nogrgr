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

    /*
    | Address lookup for the "Select address on map" button (Localization module):
    | reverse geocoding (lat/lng → street, city, state, country) and the IP fallback.
    | Providers: App\Modules\Localization\Enums\GeocodingProvider. The User-Agent names
    | the application only (Nominatim usage policy); never put personal data in it.
    */
    'geocoding' => [
        'reverse_provider' => env('GEOCODING_REVERSE_PROVIDER', 'nominatim'),
        'ip_provider' => env('GEOCODING_IP_PROVIDER', 'ipwhois'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'NogrgrStorefront/1.0 (address lookup)'),
        'timeout' => (int) env('GEOCODING_TIMEOUT', 5),
        'cache_ttl' => (int) env('GEOCODING_CACHE_TTL', 60 * 60 * 24 * 7),
        'nominatim' => [
            'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
            'email' => env('NOMINATIM_CONTACT_EMAIL'),
        ],
        'ipwhois' => [
            'url' => env('IPWHOIS_URL', 'https://ipwho.is'),
        ],
    ],

];
