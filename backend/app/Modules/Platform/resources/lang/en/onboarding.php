<?php

return [
    'title' => 'Store setup',
    'welcome' => 'Welcome, :name',
    'subtitle' => 'Finish these steps to start selling',
    'of' => ':done of :total',
    'tabs' => [
        'tour' => 'Tour',
        'checklist' => 'Setup checklist',
    ],
    'steps' => [
        'store_details' => [
            'title' => 'Store details',
            'detail' => 'Name, logo, contact',
            'available_after' => 'Editable here once Store settings are set up; your account manager can update them meanwhile.',
        ],
        'countries_languages' => [
            'title' => 'Countries & languages',
            'detail' => 'Where you sell and which languages your store speaks',
            'summary' => '{1} :count country · :languages|[2,*] :count countries · :languages',
            'available_after' => 'Editable here once Selling countries are set up; chosen at sign-up.',
        ],
        'theme' => [
            'title' => 'Theme',
            'detail' => 'Pick the look of your storefront',
            'available_after' => 'Editable here once Themes are set up; chosen at sign-up.',
        ],
        'import_products' => [
            'title' => 'Import products',
            'detail' => 'Add central products to your store',
            'available_after' => 'Available after the product catalog is set up in your panel.',
        ],
        'logo' => [
            'title' => 'Upload your logo',
            'detail' => 'PNG, JPG or WebP',
        ],
        'payment_readiness' => [
            'title' => 'Payment readiness',
            'detail' => 'Add a gateway or enable COD',
            'available_after' => 'Available after Payment gateways are set up; you can skip this step for now.',
        ],
        'domain' => [
            'title' => 'Connect a domain',
            'detail' => 'Optional',
            'available_after' => 'Available after Domains are set up; request one from your owner account meanwhile.',
        ],
        'tour' => [
            'title' => 'Take the panel tour',
            'detail' => 'A short walk through your vendor panel',
        ],
    ],
    'tour' => [
        'title' => 'Panel tour',
        'intro' => 'Six quick stops through the places you will use every day.',
        'card_title' => 'Product tour',
        'card_step' => 'Step :step of :total — :title: :body',
        'step_of' => 'Step :step of :total',
        'open' => 'Open',
        'back' => 'Back',
        'next' => 'Next',
        'skip' => 'Skip tour',
        'complete' => 'Finish tour',
        'start' => 'Start tour',
        'finished' => 'Tour finished',
        'completed' => 'Tour finished. Here is your setup checklist.',
        'dismissed' => 'Tour skipped. You can take it any time from the Tour tab.',
        'stops' => [
            'dashboard' => [
                'title' => 'Dashboard',
                'body' => 'Your store at a glance: setup progress, store power and, as you sell, sales, orders and opportunities.',
                'short' => 'see how your store is doing and what to do next',
            ],
            'products' => [
                'title' => 'Products',
                'body' => 'Import central products, set your markup per country and publish them to your storefront.',
                'short' => 'import central products, set your markup per country and publish',
            ],
            'orders' => [
                'title' => 'Orders',
                'body' => 'Follow every order from checkout to delivery and keep your customers informed.',
                'short' => 'follow every order from checkout to delivery',
            ],
            'store' => [
                'title' => 'Your Store',
                'body' => 'Theme, home page sections, banners and pages: everything your customers see.',
                'short' => 'shape the storefront your customers see',
            ],
            'finance' => [
                'title' => 'Finance',
                'body' => 'Your subscription, invoices and what you owe or earn, in one place.',
                'short' => 'track your subscription, invoices and earnings',
            ],
            'settings' => [
                'title' => 'Settings',
                'body' => 'Languages, selling countries, domains, payment gateways and e-mail templates.',
                'short' => 'languages, countries, domains and payments',
            ],
        ],
    ],
    'payment' => [
        'skip' => 'Skip for now',
        'skipped' => 'Payment readiness skipped. You can add a gateway later.',
    ],
    'logo' => [
        'title' => 'Upload your logo',
        'hint' => 'Shown in your storefront header and on your panel.',
        'field' => 'Logo',
        'rules' => 'PNG, JPG or WebP · at least 64 × 64 px · up to 2 MB',
        'current' => 'Current logo',
        'upload' => 'Upload',
        'replace' => 'Replace',
        'save' => 'Save logo',
        'saved' => 'Logo saved.',
    ],
    'widget' => [
        'title' => 'Setup progress',
        'store_setup' => 'Store setup',
        'steps' => ':done / :total steps',
        'percent' => ':percent% done',
        'next' => 'Next: :step',
        'continue' => 'Continue setup',
        'complete' => 'Your store setup is complete.',
    ],
];
