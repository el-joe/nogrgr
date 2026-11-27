<?php

/*
| Central theme gallery + live preview (WEB-004) and the platform demo store bar.
*/

return [
    'seo' => [
        'title' => 'Store themes',
        'description' => 'Browse every Nogrgr store theme and try each one live on desktop, tablet and mobile before you start your store.',
        'preview_title' => ':theme theme — live preview',
    ],
    'gallery' => [
        'heading' => 'Pick the look of your store',
        'intro' => 'Every theme is ready to sell, works in English and Arabic and looks great on phones. Open a live preview, then start with the one you like.',
        'count' => '{1} :count theme|[2,*] :count themes',
        'preview' => 'Live preview',
        'preview_named' => 'Live preview of :theme',
        'empty_title' => 'Themes are on their way',
        'empty_text' => 'No theme is available right now. Please check back soon.',
        'empty_cta' => 'Back to home',
    ],
    'preview' => [
        'all' => 'All themes',
        'tag' => 'Live demo',
        'switch' => 'Switch theme',
        'previous' => 'Previous theme: :theme',
        'next' => 'Next theme: :theme',
        'device_label' => 'Preview size',
        'devices' => [
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            'mobile' => 'Mobile',
        ],
        'language' => 'Preview language',
        'cta' => 'Use this template',
        'loading' => 'Loading :theme…',
        'frame_title' => 'Live preview of the :theme theme',
        'no_demo' => 'The live demo store is being prepared. Here is a full-page look at this theme.',
    ],
    'demo' => [
        'label' => 'Demo store',
        'title' => 'Demo store',
        'note' => 'Browse freely. Cart, checkout and accounts are turned off here.',
        'blocked' => 'This is a demo store, so this action is turned off. Start your own store to sell for real.',
    ],
];
