<?php

/*
| Vendor (store) panel sidebar — same shape as config/navigation/admin.php. Items are
| added by each module as its vendor pages ship; missing routes never render.
*/

return [
    [
        'key' => 'overview',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.admin.dashboard', 'icon' => 'dashboard', 'route' => 'vendor.dashboard', 'permission' => 'dashboard.view', 'active' => 'vendor.dashboard'],
        ],
    ],
    [
        'key' => 'requests',
        'label' => 'navigation.sections.requests',
        'items' => [
            ['key' => 'requests.tenant-changes', 'label' => 'navigation.vendor.change_requests', 'icon' => 'message', 'route' => 'vendor.requests.tenant-changes.index', 'permission' => 'requests.tenant-changes.view'],
        ],
    ],
    [
        'key' => 'finance',
        'label' => 'navigation.sections.finance_growth',
        'items' => [
            ['key' => 'finance.subscription', 'label' => 'navigation.vendor.subscription', 'icon' => 'credit-card', 'route' => 'vendor.finance.subscription', 'permission' => 'finance.subscription.view', 'active' => 'vendor.finance.subscription*'],
        ],
    ],
    [
        'key' => 'system',
        'label' => 'navigation.sections.system',
        'items' => [
            ['key' => 'settings', 'label' => 'navigation.admin.settings', 'icon' => 'settings', 'children' => [
                ['key' => 'settings.languages', 'label' => 'navigation.admin.languages', 'route' => 'vendor.settings.languages.index', 'permission' => 'settings.languages.view'],
                ['key' => 'settings.countries', 'label' => 'navigation.vendor.selling_countries', 'route' => 'vendor.settings.countries.edit', 'permission' => 'settings.countries.view'],
                ['key' => 'settings.domains', 'label' => 'navigation.vendor.domains', 'route' => 'vendor.settings.domains.index', 'permission' => 'settings.domains.view'],
                ['key' => 'settings.payment-gateways', 'label' => 'navigation.vendor.payment_gateways', 'route' => 'vendor.settings.payment-gateways.index', 'permission' => 'settings.payment-gateways.view'],
                ['key' => 'settings.email-templates', 'label' => 'navigation.admin.email_templates', 'route' => 'vendor.settings.email-templates.index', 'permission' => 'settings.email-templates.view'],
                ['key' => 'settings.compliance', 'label' => 'navigation.vendor.compliance', 'route' => 'vendor.settings.compliance.edit', 'permission' => 'settings.compliance.view'],
            ]],
            ['key' => 'notifications', 'label' => 'navigation.admin.notifications', 'icon' => 'bell', 'route' => 'vendor.notifications.index', 'permission' => 'notifications.view'],
        ],
    ],
];
