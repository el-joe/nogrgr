<?php

/*
| Vendor (store) panel sidebar — client "Side menu" 12:15498 (decision #101): the 11
| flat items of the Tenant Panel design, in its order. Sub-pages are the children of
| an item; the item links to its first reachable child and lists the children,
| indented, while one of them is active (Figma 180:28706). Finance lives under
| Settings; Support, Help center and Notifications are in the topbar store menu
| (vendor::topbar). Missing routes / permissions never render.
*/

return [
    [
        'key' => 'main',
        'label' => null,
        'items' => [
            ['key' => 'home', 'label' => 'navigation.admin.dashboard', 'icon' => 'tp-home', 'badge' => 'setup', 'children' => [
                ['key' => 'dashboard', 'label' => 'navigation.vendor.overview', 'route' => 'vendor.dashboard', 'permission' => 'dashboard.view', 'active' => 'vendor.dashboard'],
                ['key' => 'onboarding', 'label' => 'navigation.vendor.onboarding', 'route' => 'vendor.onboarding', 'permission' => 'dashboard.view', 'badge' => 'setup', 'active' => 'vendor.onboarding*'],
            ]],
            ['key' => 'catalog.opportunities', 'label' => 'navigation.vendor.opportunities', 'icon' => 'tp-zap', 'route' => 'vendor.catalog.opportunities.index', 'permission' => 'catalog.products.view'],
            ['key' => 'catalog', 'label' => 'navigation.vendor.products', 'icon' => 'tp-products', 'children' => [
                ['key' => 'catalog.products', 'label' => 'navigation.vendor.all_products', 'route' => 'vendor.catalog.products.index', 'permission' => 'catalog.products.view', 'active' => ['vendor.catalog.products.index', 'vendor.catalog.products.edit']],
                ['key' => 'catalog.new', 'label' => 'navigation.vendor.new_products', 'route' => 'vendor.catalog.products.new', 'permission' => 'catalog.products.manage', 'active' => ['vendor.catalog.products.new', 'vendor.catalog.products.new.*']],
                ['key' => 'catalog.own-products', 'label' => 'navigation.vendor.own_products', 'route' => 'vendor.catalog.own-products.index', 'permission' => 'catalog.own-products.view', 'active' => ['vendor.catalog.own-products.index', 'vendor.catalog.own-products.show', 'vendor.catalog.own-products.create', 'vendor.catalog.own-products.edit']],
                ['key' => 'catalog.categories', 'label' => 'navigation.vendor.store_categories', 'route' => 'vendor.catalog.categories.index', 'permission' => 'catalog.categories.view', 'active' => ['vendor.catalog.categories.index', 'vendor.catalog.categories.products', 'vendor.catalog.categories.create', 'vendor.catalog.categories.edit']],
                ['key' => 'catalog.badges', 'label' => 'navigation.vendor.store_badges', 'route' => 'vendor.catalog.badges.index', 'permission' => 'catalog.badges.view', 'active' => 'vendor.catalog.badges.*'],
                ['key' => 'catalog.own-inventory', 'label' => 'navigation.vendor.inventory', 'route' => 'vendor.catalog.own-products.inventory', 'permission' => 'catalog.own-products.view'],
                ['key' => 'catalog.edit-requests', 'label' => 'navigation.vendor.edit_requests', 'route' => 'vendor.catalog.edit-requests.index', 'permission' => 'catalog.products.view'],
                ['key' => 'catalog.sort', 'label' => 'navigation.vendor.product_sort', 'route' => 'vendor.catalog.products.sort', 'permission' => 'catalog.products.manage', 'active' => ['vendor.catalog.products.sort', 'vendor.catalog.categories.sort']],
            ]],
            ['key' => 'sales', 'label' => 'navigation.vendor.orders', 'icon' => 'tp-orders', 'children' => [
                ['key' => 'sales.orders', 'label' => 'navigation.vendor.all_orders', 'route' => 'vendor.sales.orders.index', 'permission' => 'orders.view', 'active' => ['vendor.sales.orders.*', 'vendor.finance.settle.*']],
                ['key' => 'sales.returns', 'label' => 'navigation.vendor.returns', 'route' => 'vendor.sales.returns.index', 'permission' => 'returns.view', 'active' => ['vendor.sales.returns.index', 'vendor.sales.returns.show']],
                ['key' => 'sales.returns-analytics', 'label' => 'navigation.vendor.returns_analytics', 'route' => 'vendor.sales.returns.analytics', 'permission' => 'returns.view'],
                ['key' => 'sales.refunds', 'label' => 'navigation.vendor.refunds', 'route' => 'vendor.sales.refunds.index', 'permission' => 'refunds.view'],
            ]],
            ['key' => 'customers', 'label' => 'navigation.vendor.customers', 'icon' => 'tp-customers', 'children' => [
                ['key' => 'sales.customers', 'label' => 'identity::customers.nav', 'route' => 'vendor.sales.customers.index', 'permission' => 'customers.view', 'active' => 'vendor.sales.customers.*'],
                ['key' => 'sales.subscribers', 'label' => 'engagement::subscribers.nav', 'route' => 'vendor.sales.subscribers.index', 'permission' => 'subscribers.view', 'active' => 'vendor.sales.subscribers.*'],
            ]],
            ['key' => 'store', 'label' => 'navigation.vendor.store_design', 'icon' => 'tp-store', 'children' => [
                ['key' => 'store.themes', 'label' => 'navigation.vendor.themes', 'route' => 'vendor.store.themes.index', 'permission' => 'store.themes.view', 'active' => 'vendor.store.themes.*'],
                ['key' => 'store.home-variants', 'label' => 'content::vendor_themes.by_country_nav', 'route' => 'vendor.store.home-variants', 'permission' => 'store.themes.view', 'active' => 'vendor.store.home-variants*'],
                ['key' => 'store.custom-themes', 'label' => 'navigation.vendor.custom_theme', 'route' => 'vendor.store.custom-themes.index', 'permission' => 'store.custom-themes.view', 'active' => 'vendor.store.custom-themes.*'],
                ['key' => 'store.page-builder', 'label' => 'navigation.vendor.page_builder', 'route' => 'vendor.store.page-builder', 'permission' => 'store.design.view', 'active' => 'vendor.store.page-builder*'],
                ['key' => 'store.banners', 'label' => 'navigation.vendor.banners', 'route' => 'vendor.store.banners.index', 'permission' => 'store.banners.view', 'active' => 'vendor.store.banners.*'],
                ['key' => 'store.pages', 'label' => 'navigation.vendor.pages', 'route' => 'vendor.store.pages.index', 'permission' => 'store.pages.view', 'active' => 'vendor.store.pages.*'],
                ['key' => 'store.appearance', 'label' => 'navigation.vendor.appearance', 'route' => 'vendor.store.appearance', 'permission' => 'store.appearance.view', 'active' => 'vendor.store.appearance*'],
                ['key' => 'store.mobile-app', 'label' => 'navigation.vendor.mobile_app', 'route' => 'vendor.store.mobile-app', 'permission' => 'store.mobile-app.view', 'active' => 'vendor.store.mobile-app*'],
            ]],
            ['key' => 'marketing', 'label' => 'navigation.vendor.marketing', 'icon' => 'tp-marketing', 'children' => [
                ['key' => 'marketing.coupons', 'label' => 'navigation.vendor.coupons', 'route' => 'vendor.marketing.coupons.index', 'permission' => 'coupons.view', 'active' => 'vendor.marketing.coupons.*'],
                ['key' => 'marketing.flash-sales', 'label' => 'navigation.vendor.flash_sales', 'route' => 'vendor.marketing.flash-sales.index', 'permission' => 'flash-sales.view', 'active' => 'vendor.marketing.flash-sales.*'],
                ['key' => 'marketing.ads', 'label' => 'navigation.vendor.ad_studio', 'route' => 'vendor.marketing.ads.index', 'permission' => 'marketing.ads.view', 'active' => 'vendor.marketing.ads.*'],
            ]],
            ['key' => 'analytics', 'label' => 'navigation.vendor.analytics', 'icon' => 'tp-analytics', 'route' => 'vendor.analytics', 'permission' => 'analytics.view', 'active' => 'vendor.analytics*'],
            ['key' => 'requests', 'label' => 'navigation.vendor.request_product', 'icon' => 'tp-request', 'badge' => 'vendor-requests', 'children' => [
                ['key' => 'requests.product', 'label' => 'navigation.vendor.product_requests', 'route' => 'vendor.requests.product.index', 'permission' => 'requests.products.view', 'badge' => 'vendor-requests.product', 'active' => 'vendor.requests.product.*'],
                ['key' => 'requests.manufacturing', 'label' => 'navigation.vendor.manufacturing', 'route' => 'vendor.requests.manufacturing.index', 'permission' => 'requests.manufacturing.view', 'badge' => 'vendor-requests.manufacturing', 'active' => 'vendor.requests.manufacturing.*'],
                ['key' => 'requests.brand', 'label' => 'navigation.vendor.brand_requests', 'route' => 'vendor.requests.brand.index', 'permission' => 'requests.brands.view', 'badge' => 'vendor-requests.brand', 'active' => 'vendor.requests.brand.*'],
                ['key' => 'requests.tenant-changes', 'label' => 'navigation.vendor.change_requests', 'route' => 'vendor.requests.tenant-changes.index', 'permission' => 'requests.tenant-changes.view'],
            ]],
            ['key' => 'partner', 'label' => 'navigation.vendor.partner_program', 'icon' => 'tp-partner', 'route' => 'vendor.partner.index', 'permission' => 'partner.view', 'active' => 'vendor.partner.*'],
            ['key' => 'settings', 'label' => 'navigation.admin.settings', 'icon' => 'tp-settings', 'children' => [
                ['key' => 'settings.account', 'label' => 'navigation.vendor.account', 'route' => 'vendor.settings.account', 'permission' => 'dashboard.view', 'active' => 'vendor.settings.account*'],
                ['key' => 'settings.general', 'label' => 'navigation.vendor.general', 'route' => 'vendor.settings.general', 'permission' => 'settings.general.view', 'active' => 'vendor.settings.general*'],
                ['key' => 'settings.currencies', 'label' => 'navigation.vendor.currencies', 'route' => 'vendor.settings.currencies', 'permission' => 'settings.currencies.view', 'active' => 'vendor.settings.currencies*'],
                ['key' => 'settings.languages', 'label' => 'navigation.admin.languages', 'route' => 'vendor.settings.languages.index', 'permission' => 'settings.languages.view'],
                ['key' => 'settings.ai-translation', 'label' => 'navigation.vendor.ai_translation', 'route' => 'vendor.settings.ai-translation', 'permission' => 'settings.languages.view', 'active' => 'vendor.settings.ai-translation*'],
                ['key' => 'settings.countries', 'label' => 'navigation.vendor.selling_countries', 'route' => 'vendor.settings.countries.edit', 'permission' => 'settings.countries.view'],
                ['key' => 'settings.domains', 'label' => 'navigation.vendor.domains', 'route' => 'vendor.settings.domains.index', 'permission' => 'settings.domains.view'],
                ['key' => 'settings.payment-gateways', 'label' => 'navigation.vendor.payment_gateways', 'route' => 'vendor.settings.payment-gateways.index', 'permission' => 'settings.payment-gateways.view'],
                ['key' => 'settings.translations', 'label' => 'navigation.vendor.translations', 'route' => 'vendor.settings.translations', 'permission' => 'settings.translations.view', 'active' => 'vendor.settings.translations*'],
                ['key' => 'settings.return-policy', 'label' => 'navigation.vendor.return_policy', 'route' => 'vendor.settings.return-policy', 'permission' => 'settings.return-policy.view', 'active' => 'vendor.settings.return-policy*'],
                ['key' => 'settings.email-templates', 'label' => 'navigation.admin.email_templates', 'route' => 'vendor.settings.email-templates.index', 'permission' => 'settings.email-templates.view', 'active' => 'vendor.settings.email-templates.*'],
                ['key' => 'settings.mail', 'label' => 'navigation.vendor.mail', 'route' => 'vendor.settings.mail', 'permission' => 'settings.mail.view', 'active' => 'vendor.settings.mail*'],
                ['key' => 'settings.tracking', 'label' => 'navigation.vendor.tracking', 'route' => 'vendor.settings.tracking', 'permission' => 'settings.tracking.view', 'active' => 'vendor.settings.tracking*'],
                ['key' => 'settings.compliance', 'label' => 'navigation.vendor.compliance', 'route' => 'vendor.settings.compliance.edit', 'permission' => 'settings.compliance.view'],
                ['key' => 'settings.staff', 'label' => 'navigation.vendor.staff', 'route' => 'vendor.settings.staff.index', 'permission' => 'settings.staff.view'],
                ['key' => 'settings.roles', 'label' => 'navigation.vendor.roles', 'route' => 'vendor.settings.roles.index', 'permission' => 'settings.roles.view', 'active' => 'vendor.settings.roles.*'],
                ['key' => 'settings.audit', 'label' => 'navigation.vendor.audit', 'route' => 'vendor.settings.audit', 'permission' => 'settings.audit.view', 'active' => 'vendor.settings.audit*'],
                ['key' => 'settings.notifications', 'label' => 'navigation.vendor.notification_preferences', 'route' => 'vendor.settings.notifications', 'permission' => 'notifications.view', 'active' => 'vendor.settings.notifications*'],
                // Internal (VEN-093, #116): impersonating admins / internal ui-kit.view only.
                ['key' => 'settings.ui-kit', 'label' => 'navigation.vendor.ui_kit', 'route' => 'vendor.ui-kit', 'permission' => 'vendor.ui-kit.view', 'active' => 'vendor.ui-kit*'],
                ['key' => 'finance.subscription', 'label' => 'navigation.vendor.subscription', 'route' => 'vendor.finance.subscription', 'permission' => 'finance.subscription.view', 'active' => 'vendor.finance.subscription*'],
                ['key' => 'finance.invoices', 'label' => 'navigation.vendor.invoices', 'route' => 'vendor.finance.invoices.index', 'permission' => 'finance.invoices.view', 'active' => 'vendor.finance.invoices.*'],
                ['key' => 'finance.purchases', 'label' => 'navigation.vendor.purchases', 'route' => 'vendor.finance.purchases.index', 'permission' => 'finance.purchases.view'],
                ['key' => 'finance.settlements', 'label' => 'navigation.vendor.settlements', 'route' => 'vendor.finance.settlements.index', 'permission' => 'finance.settlements.view'],
                ['key' => 'finance.payouts', 'label' => 'navigation.vendor.payouts', 'route' => 'vendor.finance.payouts.index', 'permission' => 'finance.payouts.view'],
            ]],
        ],
    ],
];
