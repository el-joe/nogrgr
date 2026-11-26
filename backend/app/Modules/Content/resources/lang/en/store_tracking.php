<?php

return [
    'title' => 'Tracking pixels',
    'subtitle' => 'Measure your ads. Pixels load only after a shopper accepts tracking cookies on your store.',
    'crumb' => 'Settings',
    'save' => 'Save',
    'pixel_id' => 'Pixel ID',
    'measurement_id' => 'Measurement ID',
    'id_of' => ':pixel ID',
    'enabled' => 'Send events',
    'not_set' => 'Not set',
    'status' => [
        'receiving' => 'Receiving',
        'off' => 'Off',
        'not_set' => 'Not set',
    ],
    'help' => [
        'meta' => 'Events Manager › Data sources › your pixel. 10–20 digits.',
        'tiktok' => 'TikTok Ads Manager › Assets › Events › your pixel code.',
        'snap' => 'Snap Ads Manager › Events Manager › your Pixel ID.',
        'ga4' => 'Google Analytics › Admin › Data streams › Measurement ID (G-…).',
    ],
    'events' => 'Sends page views, product views, add to cart, checkout and purchases.',
    'consent_note' => 'Shoppers see a cookie notice on your store while at least one pixel is on.',
    'saved' => 'Tracking pixels saved.',
    'required' => 'Enter the :pixel ID or switch it off.',
    'invalid' => [
        'meta' => 'A Meta pixel ID has 10–20 digits.',
        'tiktok' => 'A TikTok pixel code has 10–30 letters and digits.',
        'snap' => 'A Snap Pixel ID looks like 8f9e2b1c-3d4a-4b5c-9d8e-1a2b3c4d5e6f.',
        'ga4' => 'A GA4 Measurement ID starts with G-.',
    ],
];
