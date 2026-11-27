<?php

return [
    'title' => 'Mobile app',
    'subtitle' => 'Versions, store links, maintenance, splash screen and features of your store app.',
    'save' => 'Save changes',
    'saved' => 'Mobile app settings saved.',
    'platforms' => [
        'ios' => 'App Store (iOS)',
        'android' => 'Google Play (Android)',
    ],
    'sections' => [
        'releases' => 'Versions & store links',
        'releases_help' => 'Below the minimum version the app asks customers to update before continuing; below the latest version it suggests an update.',
        'maintenance' => 'Maintenance',
        'maintenance_help' => 'While on, the app shows this message instead of the store.',
        'splash' => 'Splash screen',
        'splash_help' => 'Shown while the app starts. Leave empty to use your store colour and logo.',
        'features' => 'Features',
        'features_help' => 'Switch app features on or off for your customers.',
        'links' => 'Store links',
        'preview' => 'Preview',
        'preview_caption' => 'Splash screen',
        'push' => 'Push notifications',
    ],
    'fields' => [
        'min_version' => 'Minimum version',
        'latest_version' => 'Latest version',
        'store_url' => 'Store listing URL',
        'maintenance' => 'App under maintenance',
        'maintenance_message' => 'Maintenance message',
        'splash_background' => 'Splash background',
        'splash_logo' => 'Splash logo',
        'remove_splash_logo' => 'Remove the splash logo',
    ],
    'placeholders' => [
        'version' => '1.4.2',
        'ios_url' => 'https://apps.apple.com/app/…',
        'android_url' => 'https://play.google.com/store/apps/details?id=…',
        'message' => 'We are improving the app. Please check back soon.',
    ],
    'states' => [
        'live' => 'Live :version',
        'not_set' => 'Not set',
    ],
    'push' => [
        'ready' => 'Push notifications are connected by the platform. Customers get order, return and support updates on their phones.',
        'missing' => 'Push notifications are not connected yet. The Nogrgr team sets this up; the app works without them.',
        'admin_missing' => 'Firebase is not configured. Push notifications stay off for every store until it is.',
        'admin_ready' => 'Firebase is configured: store apps can receive push notifications.',
        'admin_link' => 'Open Firebase integration',
    ],
    'admin' => [
        'unavailable' => 'The store is not live yet: its app settings can be edited once it is provisioned.',
        'save' => 'Save app settings',
        'tab_intro' => 'The store\'s app settings. Changes apply to its app on the next start.',
    ],
    'errors' => [
        'latest_below_min' => 'The latest version cannot be lower than the minimum version.',
    ],
];
