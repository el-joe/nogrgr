<?php

/*
|--------------------------------------------------------------------------
| Admin sidebar (docs/plan/design-specs.md §4, Figma IA map 57:17014)
|--------------------------------------------------------------------------
|
| Sections → items → optional children. Rendered by <x-ui::sidebar> through
| App\Shared\Navigation\NavigationBuilder, which drops every item whose route is not
| registered (modules that have not shipped yet) or whose permission the admin
| lacks, then drops empty groups and sections. Nothing renders as a dead link.
|
| item keys: key, label (translation key), icon (resources/svg/icons), route,
|            permission, active (route pattern or list of patterns, default "<route without .index>*"),
|            badge (key into the live counts array), children (same shape).
*/

return [
    [
        'key' => 'overview',
        'label' => null,
        'items' => [
            ['key' => 'dashboard', 'label' => 'navigation.admin.dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view', 'active' => 'admin.dashboard'],
        ],
    ],
    [
        'key' => 'catalog',
        'label' => 'navigation.sections.catalog',
        'items' => [
            ['key' => 'products', 'label' => 'navigation.admin.products', 'icon' => 'box', 'children' => [
                ['key' => 'products.index', 'label' => 'navigation.admin.all_products', 'route' => 'admin.catalog.products.index', 'permission' => 'catalog.products.view'],
                ['key' => 'products.create', 'label' => 'navigation.admin.add_product', 'route' => 'admin.catalog.products.create', 'permission' => 'catalog.products.manage', 'active' => 'admin.catalog.products.create'],
                ['key' => 'products.import', 'label' => 'navigation.admin.import_products', 'route' => 'admin.catalog.imports.index', 'permission' => 'catalog.imports.view'],
                ['key' => 'products.sort', 'label' => 'navigation.admin.sort_products', 'route' => 'admin.catalog.products.sort', 'permission' => 'catalog.products.manage', 'active' => 'admin.catalog.products.sort'],
            ]],
            ['key' => 'categories', 'label' => 'navigation.admin.categories', 'icon' => 'layers', 'route' => 'admin.catalog.categories.index', 'permission' => 'catalog.categories.view'],
            ['key' => 'attributes', 'label' => 'navigation.admin.attributes', 'icon' => 'list', 'route' => 'admin.catalog.attributes.index', 'permission' => 'catalog.attributes.view'],
            ['key' => 'labels', 'label' => 'navigation.admin.labels_badges', 'icon' => 'tag', 'children' => [
                ['key' => 'labels.index', 'label' => 'navigation.admin.product_labels', 'route' => 'admin.catalog.labels.index', 'permission' => 'catalog.labels.view'],
                ['key' => 'badges.index', 'label' => 'navigation.admin.product_badges', 'route' => 'admin.catalog.badges.index', 'permission' => 'catalog.badges.view'],
            ]],
        ],
    ],
    [
        'key' => 'requests',
        'label' => 'navigation.sections.requests',
        'items' => [
            ['key' => 'requests', 'label' => 'navigation.admin.requests_center', 'icon' => 'message', 'badge' => 'requests', 'children' => [
                ['key' => 'requests.product', 'label' => 'navigation.admin.product_requests', 'route' => 'admin.requests.products.index', 'permission' => 'requests.products.view'],
                ['key' => 'requests.edit', 'label' => 'navigation.admin.edit_requests', 'route' => 'admin.requests.edits.index', 'permission' => 'requests.edits.view'],
                ['key' => 'requests.manufacturing', 'label' => 'navigation.admin.manufacturing_requests', 'route' => 'admin.requests.manufacturing.index', 'permission' => 'requests.manufacturing.view'],
                ['key' => 'requests.brand', 'label' => 'navigation.admin.brand_requests', 'route' => 'admin.requests.brands.index', 'permission' => 'requests.brands.view'],
                ['key' => 'requests.tenant', 'label' => 'navigation.admin.tenant_change_requests', 'route' => 'admin.requests.tenant-changes.index', 'permission' => 'requests.tenant-changes.view'],
            ]],
        ],
    ],
    [
        'key' => 'sales',
        'label' => 'navigation.sections.sales',
        'items' => [
            ['key' => 'orders', 'label' => 'navigation.admin.orders', 'icon' => 'cart', 'route' => 'admin.orders.index', 'permission' => 'orders.view', 'badge' => 'orders'],
            ['key' => 'shipping-queue', 'label' => 'navigation.admin.shipping_queue', 'icon' => 'truck', 'route' => 'admin.shipping-queue.index', 'permission' => 'shipping-queue.view'],
            ['key' => 'returns', 'label' => 'navigation.admin.returns_refunds', 'icon' => 'return', 'badge' => 'returns', 'children' => [
                ['key' => 'returns.index', 'label' => 'navigation.admin.return_requests', 'route' => 'admin.returns.index', 'permission' => 'returns.view'],
                ['key' => 'refunds.index', 'label' => 'navigation.admin.refunds', 'route' => 'admin.refunds.index', 'permission' => 'refunds.view'],
            ]],
            ['key' => 'promotions', 'label' => 'navigation.admin.promotions', 'icon' => 'percent', 'children' => [
                ['key' => 'coupons.index', 'label' => 'navigation.admin.coupons', 'route' => 'admin.promotions.coupons.index', 'permission' => 'coupons.view'],
                ['key' => 'flash-sales.index', 'label' => 'navigation.admin.flash_sales', 'route' => 'admin.promotions.flash-sales.index', 'permission' => 'flash-sales.view'],
            ]],
        ],
    ],
    [
        'key' => 'logistics',
        'label' => 'navigation.sections.logistics',
        'items' => [
            ['key' => 'shipping', 'label' => 'navigation.admin.shipping', 'icon' => 'map', 'children' => [
                ['key' => 'shipping.companies', 'label' => 'navigation.admin.shipping_companies', 'route' => 'admin.shipping.companies.index', 'permission' => 'shipping.companies.view'],
                ['key' => 'shipping.rules', 'label' => 'navigation.admin.rules_zones', 'route' => 'admin.shipping.rules.index', 'permission' => 'shipping.rules.view'],
                ['key' => 'shipping.fixed', 'label' => 'navigation.admin.fixed_shipping_costs', 'route' => 'admin.shipping.fixed-costs.index', 'permission' => 'shipping.fixed-costs.view'],
                ['key' => 'shipping.stops', 'label' => 'navigation.admin.stop_periods', 'route' => 'admin.shipping.stop-periods.index', 'permission' => 'shipping.stop-periods.view'],
                ['key' => 'shipping.popup', 'label' => 'navigation.admin.delivery_popup_days', 'route' => 'admin.shipping.delivery-days.index', 'permission' => 'shipping.delivery-days.view'],
            ]],
            ['key' => 'branches', 'label' => 'navigation.admin.branches', 'icon' => 'building', 'route' => 'admin.shipping.branches.index', 'permission' => 'shipping.branches.view'],
        ],
    ],
    [
        'key' => 'vendors',
        'label' => 'navigation.sections.vendors',
        'items' => [
            ['key' => 'vendors', 'label' => 'navigation.admin.vendors', 'icon' => 'store', 'badge' => 'vendors', 'children' => [
                ['key' => 'vendors.index', 'label' => 'navigation.admin.all_vendors', 'route' => 'admin.vendors.index', 'permission' => 'vendors.view', 'active' => ['admin.vendors.index', 'admin.vendors.create', 'admin.vendors.show']],
                ['key' => 'vendors.pending', 'label' => 'navigation.admin.pending_registrations', 'route' => 'admin.vendors.pending', 'permission' => 'vendors.view', 'active' => 'admin.vendors.pending*'],
                ['key' => 'vendors.compliance', 'label' => 'navigation.admin.compliance_kyc', 'route' => 'admin.vendors.compliance.index', 'permission' => 'vendors.kyc.view', 'active' => 'admin.vendors.compliance.*'],
                ['key' => 'vendors.domains', 'label' => 'navigation.admin.domains_dns', 'route' => 'admin.vendors.domains.index', 'permission' => 'vendors.domains.view', 'active' => 'admin.vendors.domains.*'],
                ['key' => 'vendors.shoppers', 'label' => 'navigation.admin.shoppers', 'route' => 'admin.vendors.shoppers.index', 'permission' => 'vendors.shoppers.view'],
            ]],
            ['key' => 'plans', 'label' => 'navigation.admin.plans', 'icon' => 'star', 'route' => 'admin.plans.index', 'permission' => 'plans.view'],
            ['key' => 'subscriptions', 'label' => 'navigation.admin.subscriptions', 'icon' => 'credit-card', 'route' => 'admin.billing.subscriptions.index', 'permission' => 'subscriptions.view'],
        ],
    ],
    [
        'key' => 'finance',
        'label' => 'navigation.sections.finance_growth',
        'items' => [
            ['key' => 'finance', 'label' => 'navigation.admin.finance', 'icon' => 'wallet', 'children' => [
                ['key' => 'finance.ledger', 'label' => 'navigation.admin.ledger', 'route' => 'admin.finance.ledger.index', 'permission' => 'finance.ledger.view'],
                ['key' => 'finance.settlements', 'label' => 'navigation.admin.settlements', 'route' => 'admin.finance.settlements.index', 'permission' => 'finance.settlements.view'],
                ['key' => 'finance.payouts', 'label' => 'navigation.admin.payouts', 'route' => 'admin.finance.payouts.index', 'permission' => 'finance.payouts.view'],
                ['key' => 'finance.transactions', 'label' => 'navigation.admin.transactions', 'route' => 'admin.finance.transactions.index', 'permission' => 'finance.transactions.view'],
                ['key' => 'finance.invoices', 'label' => 'navigation.admin.invoices', 'route' => 'admin.finance.invoices.index', 'permission' => 'finance.invoices.view'],
                ['key' => 'finance.payment-logs', 'label' => 'navigation.admin.payment_logs', 'route' => 'admin.finance.payment-logs.index', 'permission' => 'finance.payment-logs.view'],
            ]],
            ['key' => 'affiliates', 'label' => 'navigation.admin.affiliates', 'icon' => 'gift', 'children' => [
                ['key' => 'affiliates.accounts', 'label' => 'navigation.admin.affiliate_accounts', 'route' => 'admin.affiliates.index', 'permission' => 'affiliates.view'],
                ['key' => 'affiliates.conversions', 'label' => 'navigation.admin.conversions', 'route' => 'admin.affiliates.conversions.index', 'permission' => 'affiliates.conversions.view'],
                ['key' => 'affiliates.payouts', 'label' => 'navigation.admin.affiliate_payouts', 'route' => 'admin.affiliates.payouts.index', 'permission' => 'affiliates.payouts.view'],
                ['key' => 'affiliates.promo-codes', 'label' => 'navigation.admin.promo_codes', 'route' => 'admin.affiliates.promo-codes.index', 'permission' => 'affiliates.promo-codes.view'],
                ['key' => 'affiliates.settings', 'label' => 'navigation.admin.program_settings', 'route' => 'admin.affiliates.settings.edit', 'permission' => 'affiliates.settings.manage'],
            ]],
            ['key' => 'reports', 'label' => 'navigation.admin.reports', 'icon' => 'chart', 'route' => 'admin.reports.index', 'permission' => 'reports.view'],
        ],
    ],
    [
        'key' => 'content',
        'label' => 'navigation.sections.content',
        'items' => [
            ['key' => 'storefront', 'label' => 'navigation.admin.storefront', 'icon' => 'palette', 'children' => [
                ['key' => 'storefront.themes', 'label' => 'navigation.admin.themes', 'route' => 'admin.storefront.themes.index', 'permission' => 'storefront.themes.view'],
                ['key' => 'storefront.theme-review', 'label' => 'navigation.admin.theme_review_queue', 'route' => 'admin.storefront.theme-reviews.index', 'permission' => 'storefront.theme-reviews.view'],
                ['key' => 'storefront.banners', 'label' => 'navigation.admin.banners', 'route' => 'admin.storefront.banners.index', 'permission' => 'storefront.banners.view'],
                ['key' => 'storefront.return-policy', 'label' => 'navigation.admin.return_policy', 'route' => 'admin.storefront.return-policy.edit', 'permission' => 'storefront.return-policy.manage'],
            ]],
            ['key' => 'website', 'label' => 'navigation.admin.website', 'icon' => 'globe', 'children' => [
                ['key' => 'website.posts', 'label' => 'navigation.admin.blog_posts', 'route' => 'admin.content.posts.index', 'permission' => 'content.posts.view'],
                ['key' => 'website.post-categories', 'label' => 'navigation.admin.blog_categories', 'route' => 'admin.content.post-categories.index', 'permission' => 'content.post-categories.view'],
                ['key' => 'website.pages', 'label' => 'navigation.admin.static_pages', 'route' => 'admin.content.pages.index', 'permission' => 'content.pages.view'],
                ['key' => 'website.faqs', 'label' => 'navigation.admin.faqs', 'route' => 'admin.content.faqs.index', 'permission' => 'content.faqs.view'],
                ['key' => 'website.newsletter', 'label' => 'navigation.admin.newsletter_subscribers', 'route' => 'admin.content.newsletter.index', 'permission' => 'content.newsletter.view'],
            ]],
        ],
    ],
    [
        'key' => 'system',
        'label' => 'navigation.sections.system',
        'items' => [
            ['key' => 'localization', 'label' => 'navigation.admin.localization', 'icon' => 'languages', 'children' => [
                ['key' => 'localization.languages', 'label' => 'navigation.admin.languages', 'route' => 'admin.localization.languages.index', 'permission' => 'localization.languages.view'],
                ['key' => 'localization.translations', 'label' => 'navigation.admin.translations', 'route' => 'admin.localization.translations.index', 'permission' => 'localization.translations.view'],
                ['key' => 'localization.key-locks', 'label' => 'navigation.admin.translation_locks', 'route' => 'admin.localization.key-locks.index', 'permission' => 'localization.key-locks.view'],
                ['key' => 'localization.currencies', 'label' => 'navigation.admin.currencies', 'route' => 'admin.localization.currencies.index', 'permission' => 'localization.currencies.view'],
                ['key' => 'localization.countries', 'label' => 'navigation.admin.countries', 'route' => 'admin.localization.countries.index', 'permission' => 'localization.countries.view'],
                ['key' => 'localization.purchases', 'label' => 'navigation.vendor.language_purchases', 'route' => 'admin.localization.purchases.index', 'permission' => 'localization.purchases.view'],
            ]],
            ['key' => 'settings', 'label' => 'navigation.admin.settings', 'icon' => 'settings', 'children' => [
                ['key' => 'settings.general', 'label' => 'navigation.admin.general', 'route' => 'admin.settings.general', 'permission' => 'settings.view', 'active' => 'admin.settings.general*'],
                ['key' => 'settings.integrations', 'label' => 'navigation.admin.integrations', 'route' => 'admin.integrations.index', 'permission' => 'integrations.view', 'active' => 'admin.integrations.*'],
                ['key' => 'settings.email-templates', 'label' => 'navigation.admin.email_templates', 'route' => 'admin.settings.email-templates.index', 'permission' => 'email-templates.view', 'active' => 'admin.settings.email-templates.*'],
            ]],
            ['key' => 'tickets', 'label' => 'navigation.admin.support_tickets', 'icon' => 'lifebuoy', 'route' => 'admin.tickets.index', 'permission' => 'tickets.view', 'badge' => 'tickets'],
            ['key' => 'notifications', 'label' => 'navigation.admin.notifications', 'icon' => 'bell', 'route' => 'admin.notifications.index', 'permission' => 'notifications.view'],
            ['key' => 'admins', 'label' => 'navigation.admin.admins_roles', 'icon' => 'shield', 'children' => [
                ['key' => 'admins.index', 'label' => 'navigation.admin.admins_list', 'route' => 'admin.system.admins.index', 'permission' => 'admins.view'],
                ['key' => 'roles.index', 'label' => 'navigation.vendor.roles', 'route' => 'admin.system.roles.index', 'permission' => 'roles.view'],
            ]],
            ['key' => 'audit', 'label' => 'navigation.admin.audit_log', 'icon' => 'file', 'route' => 'admin.system.audit.index', 'permission' => 'audit.view'],
            ['key' => 'system-tools', 'label' => 'navigation.admin.system_tools', 'icon' => 'server', 'children' => [
                ['key' => 'system.maintenance', 'label' => 'navigation.admin.maintenance_windows', 'route' => 'admin.system.maintenance.index', 'permission' => 'system.maintenance.view'],
                ['key' => 'system.cache', 'label' => 'navigation.admin.cache_tools', 'route' => 'admin.system.cache.index', 'permission' => 'system.cache.manage'],
                ['key' => 'system.docs', 'label' => 'navigation.admin.developer_docs', 'route' => 'admin.system.docs', 'permission' => 'system.docs.view'],
                ['key' => 'system.ui-kit', 'label' => 'navigation.admin.ui_kit', 'route' => 'admin.ui-kit', 'permission' => 'system.ui-kit.view', 'active' => 'admin.ui-kit'],
            ]],
        ],
    ],
];
