<?php

/*
|--------------------------------------------------------------------------
| Third-party services (decision #69)
|--------------------------------------------------------------------------
|
| No credentials or endpoints come from .env. Everything below is a safe default that
| IntegrationConfigOverlay replaces with Admin › Integrations values (Google / Apple login,
| Cloudflare, Geocoding, …). A section that was never saved stays "not configured".
|
*/

return [

    // Framework defaults neutralised (merged from the framework config otherwise): the
    // Mail tab only offers SMTP / log, and Slack notifications are not used.
    'postmark' => ['key' => null],
    'resend' => ['key' => null],
    'ses' => ['key' => null, 'secret' => null, 'region' => null],
    'slack' => ['notifications' => ['bot_user_oauth_token' => null, 'channel' => null]],

    /*
    | Address lookup for "Select address on map" (Localization module): reverse geocoding
    | (lat/lng → street, city, state, country) and the IP fallback. Admin › Integrations ›
    | Geocoding. Providers: App\Modules\Localization\Enums\GeocodingProvider. The User-Agent
    | names the application only (Nominatim usage policy); never put personal data in it.
    */
    /*
    | Firebase Cloud Messaging (mobile push, P11). Filled from Admin › Integrations ›
    | Firebase (decision #69): project id + service account JSON key.
    */
    'fcm' => [
        'enabled' => false,
        'project_id' => null,
        'service_account' => null,
    ],

    'geocoding' => [
        'enabled' => false,
        'reverse_provider' => 'nominatim',
        'ip_provider' => 'ipwhois',
        'user_agent' => 'NogrgrStorefront/1.0 (address lookup)',
        'timeout' => 5,
        'cache_ttl' => 60 * 60 * 24 * 7,
        'nominatim' => [
            'url' => 'https://nominatim.openstreetmap.org',
            'email' => null,
        ],
        'ipwhois' => [
            'url' => 'https://ipwho.is',
        ],
    ],

];
