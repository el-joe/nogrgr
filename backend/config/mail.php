<?php

/*
|--------------------------------------------------------------------------
| Mail (decision #69)
|--------------------------------------------------------------------------
|
| Outgoing mail is configured only in Admin › Integrations › Mail (SMTP or log),
| applied at runtime by IntegrationConfigOverlay. Until then mail goes to a
| non-delivering sink: MAIL_MAILER may only pick `log` (default) or `array`
| (tests); it can never select a real transport or carry credentials.
|
*/

$sink = env('MAIL_MAILER', 'log');

return [

    'default' => in_array($sink, ['log', 'array'], true) ? $sink : 'log',

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => null,
            'url' => null,
            'host' => null,
            'port' => 587,
            'username' => null,
            'password' => null,
            'timeout' => null,
            'local_domain' => parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => null,
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => 'no-reply@'.(parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
        'name' => env('APP_NAME', 'Nogrgr'),
    ],

];
