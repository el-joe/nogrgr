<?php

/*
|--------------------------------------------------------------------------
| Broadcasting (decision #69)
|--------------------------------------------------------------------------
|
| Reverb / Pusher app credentials and the client host come only from Admin ›
| Integrations › Reverb/Pusher (IntegrationConfigOverlay sets the connection and
| makes it the default once enabled). BROADCAST_CONNECTION may only choose a
| non-delivering sink (`null` default, or `log`) until then. The Reverb server
| process bind (REVERB_SERVER_HOST/PORT/PATH) stays in config/reverb.php.
|
*/

$sink = env('BROADCAST_CONNECTION', 'null');

return [

    'default' => in_array($sink, ['null', 'log'], true) ? $sink : 'null',

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => null,
            'secret' => null,
            'app_id' => null,
            'options' => [
                'host' => null,
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
            ],
            'client_options' => [],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => null,
            'secret' => null,
            'app_id' => null,
            'options' => [
                'cluster' => 'mt1',
                'host' => null,
                'port' => 443,
                'scheme' => 'https',
                'encrypted' => true,
                'useTLS' => true,
            ],
            'client_options' => [],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => null,
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
