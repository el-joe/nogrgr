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
            ['key' => 'onboarding', 'label' => 'navigation.vendor.onboarding', 'icon' => 'check-circle', 'route' => 'vendor.onboarding', 'permission' => 'dashboard.view', 'badge' => 'setup', 'active' => 'vendor.onboarding*'],
            ['key' => 'catalog.opportunities', 'label' => 'navigation.vendor.opportunities', 'icon' => 'zap', 'route' => 'vendor.catalog.opportunities.index', 'permission' => 'catalog.products.view'],
            ['key' => 'catalog', 'label' => 'navigation.vendor.products', 'icon' => 'box', 'children' => [
                ['key' => 'catalog.products', 'label' => 'navigation.vendor.all_products', 'route' => 'vendor.catalog.products.index', 'permission' => 'catalog.products.view', 'active' => ['vendor.catalog.products.index', 'vendor.catalog.products.edit']],
                ['key' => 'catalog.new', 'label' => 'navigation.vendor.new_products', 'route' => 'vendor.catalog.products.new', 'permission' => 'catalog.products.view', 'active' => ['vendor.catalog.products.new', 'vendor.catalog.products.new.*']],
                ['key' => 'catalog.own-products', 'label' => 'navigation.vendor.own_products', 'route' => 'vendor.catalog.own-products.index', 'permission' => 'catalog.own-products.view', 'active' => ['vendor.catalog.own-products.index', 'vendor.catalog.own-products.show', 'vendor.catalog.own-products.create', 'vendor.catalog.own-products.edit']],
                ['key' => 'catalog.own-inventory', 'label' => 'navigation.vendor.inventory', 'route' => 'vendor.catalog.own-products.inventory', 'permission' => 'catalog.own-products.view'],
                ['key' => 'catalog.edit-requests', 'label' => 'navigation.vendor.edit_requests', 'route' => 'vendor.catalog.edit-requests.index', 'permission' => 'catalog.products.view'],
                ['key' => 'catalog.sort', 'label' => 'navigation.vendor.product_sort', 'route' => 'vendor.catalog.products.sort', 'permission' => 'catalog.products.view'],
            ]],
            ['key' => 'sales', 'label' => 'navigation.vendor.orders', 'icon' => 'cart', 'children' => [
                ['key' => 'sales.orders', 'label' => 'navigation.vendor.all_orders', 'route' => 'vendor.sales.orders.index', 'permission' => 'orders.view', 'active' => ['vendor.sales.orders.*', 'vendor.finance.settle.*']],
                ['key' => 'sales.returns', 'label' => 'navigation.vendor.returns', 'route' => 'vendor.sales.returns.index', 'permission' => 'returns.view'],
                ['key' => 'sales.refunds', 'label' => 'navigation.vendor.refunds', 'route' => 'vendor.sales.refunds.index', 'permission' => 'refunds.view'],
            ]],
        ],
    ],
    [
        'key' => 'requests',
        'label' => 'navigation.sections.requests',
        'items' => [
            ['key' => 'requests', 'label' => 'navigation.vendor.requests', 'icon' => 'message', 'badge' => 'vendor-requests', 'children' => [
                ['key' => 'requests.product', 'label' => 'navigation.vendor.product_requests', 'route' => 'vendor.requests.product.index', 'permission' => 'requests.products.view', 'badge' => 'vendor-requests.product', 'active' => 'vendor.requests.product.*'],
                ['key' => 'requests.manufacturing', 'label' => 'navigation.vendor.manufacturing', 'route' => 'vendor.requests.manufacturing.index', 'permission' => 'requests.manufacturing.view', 'badge' => 'vendor-requests.manufacturing', 'active' => 'vendor.requests.manufacturing.*'],
                ['key' => 'requests.brand', 'label' => 'navigation.vendor.brand_requests', 'route' => 'vendor.requests.brand.index', 'permission' => 'requests.brands.view', 'badge' => 'vendor-requests.brand', 'active' => 'vendor.requests.brand.*'],
            ]],
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
