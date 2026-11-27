<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\Guard;
use Illuminate\Support\Str;

/**
 * Single source of permission names per guard. Modules add their permissions here
 * (or via their own seeders) as they are built. Convention: "<resource>.<ability>",
 * where "manage" implies "view" (enforced by the Gate::before hook in the Identity
 * provider and by RoleService when a role is saved).
 */
final class PermissionCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public static function central(): array
    {
        return [
            Guard::Admin->value => [
                'dashboard.view',
                'horizon.view',
                'tenants.impersonate',
                'system.ui-kit.view',
                // Identity
                'admins.view', 'admins.manage',
                'roles.view', 'roles.manage',
                'audit.view', 'audit.export',
                // Localization
                'localization.languages.view', 'localization.languages.manage',
                'localization.currencies.view', 'localization.currencies.manage',
                'localization.countries.view', 'localization.countries.manage',
                'localization.purchases.view',
                'localization.translations.view', 'localization.translations.manage',
                'localization.key-locks.view', 'localization.key-locks.manage',
                // Platform: vendors, registrations, domains, compliance, change requests
                'vendors.view', 'vendors.manage', 'vendors.delete', 'vendors.export',
                'vendors.kyc.view', 'vendors.kyc.manage',
                'vendors.domains.view', 'vendors.domains.manage',
                'vendors.limits.manage',
                'requests.tenant-changes.view', 'requests.tenant-changes.manage',
                'requests.products.view', 'requests.products.manage',
                'requests.edits.view', 'requests.edits.manage',
                'requests.manufacturing.view', 'requests.manufacturing.manage',
                'requests.brands.view', 'requests.brands.manage',
                // Billing (plans, subscriptions)
                'plans.view', 'plans.manage',
                'subscriptions.view', 'subscriptions.manage', 'subscriptions.export',
                'finance.invoices.view',
                'finance.payment-logs.view', 'finance.payment-logs.manage',
                // Integrations, Notifications, Content (email templates)
                'integrations.view', 'integrations.manage',
                'settings.view', 'settings.manage',
                'notifications.view',
                'email-templates.view', 'email-templates.manage',
                // Catalog taxonomy
                'catalog.categories.view', 'catalog.categories.manage',
                'catalog.attributes.view', 'catalog.attributes.manage',
                'catalog.labels.view', 'catalog.labels.manage',
                'catalog.badges.view', 'catalog.badges.manage',
                'catalog.products.view', 'catalog.products.manage', 'catalog.products.export',
                'catalog.imports.view', 'catalog.imports.manage',
                // Inventory: branches
                'shipping.branches.view', 'shipping.branches.manage',
                // Shipping (P4)
                'shipping.companies.view', 'shipping.companies.manage',
                'shipping.rules.view', 'shipping.rules.manage',
                'shipping.fixed-costs.view', 'shipping.fixed-costs.manage',
                'shipping.stop-periods.view', 'shipping.stop-periods.manage',
                'shipping.delivery-popup.view', 'shipping.delivery-popup.manage',
                'shipping.settings.view', 'shipping.settings.manage',
                // Commerce: promotions (P5)
                'coupons.view', 'coupons.manage',
                'flash-sales.view', 'flash-sales.manage',
                // Commerce: orders (P5)
                'orders.view', 'orders.manage',
                // Commerce / AfterSales: fulfilment, returns, refunds, return policy (P5 batch 3)
                'orders.export',
                'shipping-queue.view', 'shipping-queue.manage',
                'returns.view', 'returns.manage',
                'refunds.view', 'refunds.manage',
                'storefront.return-policy.view', 'storefront.return-policy.manage',
                // Storefront: default banners (P7, ADM-085)
                'storefront.banners.view', 'storefront.banners.manage',
                // Storefront: themes catalogue + home sections, template parts (ADM-086 / 087 / 088)
                'storefront.themes.view', 'storefront.themes.manage',
                'storefront.template-parts.view', 'storefront.template-parts.manage',
                'storefront.theme-reviews.view', 'storefront.theme-reviews.manage',
                // Billing: ledger, settlements, payouts, transactions (P5 batch 4)
                'finance.ledger.view', 'finance.ledger.manage',
                'finance.settlements.view',
                'finance.payouts.view', 'finance.payouts.manage',
                'finance.transactions.view',
                // Content: central website (P6)
                'content.posts.view', 'content.posts.manage',
                'content.post-categories.view', 'content.post-categories.manage',
                'content.pages.view', 'content.pages.manage',
                'content.faqs.view', 'content.faqs.manage',
                'content.newsletter.view', 'content.newsletter.manage', 'content.newsletter.export',
                'content.contact.view',
                // Reporting: reports hub, shoppers (P9, ADM-070 / ADM-043); developer docs (ADM-115)
                'reports.view', 'reports.export',
                'vendors.shoppers.view', 'vendors.shoppers.export',
                'system.docs.view',
                // System tools: maintenance windows (ADM-113), cache tools (ADM-114)
                'system.maintenance.view', 'system.maintenance.manage',
                'system.cache.view', 'system.cache.manage',
                // Support tickets (P9, ADM-116)
                'support.tickets.view', 'support.tickets.manage',
                // Affiliate program (P9, ADM-060…063)
                'affiliates.view', 'affiliates.manage',
                'affiliates.conversions.view', 'affiliates.conversions.manage',
                'affiliates.payouts.view', 'affiliates.payouts.manage',
                'affiliates.promo-codes.view', 'affiliates.promo-codes.manage',
                'affiliates.settings.manage',
            ],
            Guard::Affiliate->value => [
                'dashboard.view', 'notifications.view',
                // Affiliate panel (P9, AFF-009…014)
                'links.view', 'conversions.view',
                'payouts.view', 'payouts.request',
                'profile.manage',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function tenant(): array
    {
        return [
            Guard::Vendor->value => [
                'dashboard.view',
                'onboarding.manage', 'settings.store.manage',
                'notifications.view',
                'settings.languages.view', 'settings.languages.manage',
                'settings.countries.view', 'settings.countries.manage',
                'settings.domains.view', 'settings.domains.manage',
                'settings.compliance.view', 'settings.compliance.manage',
                'settings.payment-gateways.view', 'settings.payment-gateways.manage',
                'settings.email-templates.view', 'settings.email-templates.manage',
                'requests.tenant-changes.view', 'requests.tenant-changes.manage',
                'requests.products.view', 'requests.products.manage',
                'requests.manufacturing.view', 'requests.manufacturing.manage',
                'requests.brands.view', 'requests.brands.manage',
                'finance.subscription.view', 'finance.subscription.manage',
                'catalog.products.view', 'catalog.products.manage', 'catalog.products.export',
                'catalog.own-products.view', 'catalog.own-products.manage',
                'catalog.categories.view', 'catalog.categories.manage',
                'catalog.badges.view', 'catalog.badges.manage',
                'orders.view', 'orders.manage', 'orders.export',
                'returns.view', 'returns.manage',
                'refunds.view', 'refunds.manage',
                // P5 batch 4: promotions, money flow
                'coupons.view', 'coupons.manage',
                'flash-sales.view', 'flash-sales.manage',
                'finance.purchases.view',
                'finance.settlements.view',
                'finance.payouts.view',
                // P8: customers
                'customers.view', 'customers.manage', 'customers.export',
                'subscribers.view', 'subscribers.manage', 'subscribers.export',
                // P8: store banners, invoices
                'store.banners.view', 'store.banners.manage',
                'finance.invoices.view',
                // P8: store themes
                'store.themes.view', 'store.themes.manage',
                'store.custom-themes.view', 'store.custom-themes.manage',
                // P8: staff & roles (VEN-076 / 077)
                'settings.staff.view', 'settings.staff.manage',
                'settings.roles.view', 'settings.roles.manage',
                // P8 batch 2: analytics, store pages & appearance, settings (VEN-024/033/034/045/070-086)
                'analytics.view',
                'store.pages.view', 'store.pages.manage',
                'store.appearance.view', 'store.appearance.manage',
                'settings.general.view', 'settings.general.manage',
                'settings.currencies.view', 'settings.currencies.manage',
                'settings.translations.view', 'settings.translations.manage',
                'settings.return-policy.view', 'settings.return-policy.manage',
                'settings.mail.view', 'settings.mail.manage',
                'settings.tracking.view', 'settings.tracking.manage',
                'settings.api-keys.view', 'settings.api-keys.manage',
                'settings.audit.view',
                // P9: support tickets (VEN-091)
                'support.view', 'support.manage',
                // P9: partner program (VEN-055 / 055a)
                'partner.view', 'partner.manage',
                // P10: AI Advertising Studio (VEN-010c / VEN-009e)
                'marketing.ads.view', 'marketing.ads.manage',
                // P11: mobile app settings (VEN-037 / 037a)
                'store.mobile-app.view', 'store.mobile-app.manage',
                // P12: page builder (VEN-032, decision #117 b)
                'store.design.view', 'store.design.manage',
                // Internal: vendor UI kit (VEN-093, decision #116) — see INTERNAL
                'ui-kit.view',
            ],
        ];
    }

    /**
     * Permissions of one guard (central or tenant scope).
     *
     * @return list<string>
     */
    public static function forGuard(string $guard): array
    {
        return self::central()[$guard] ?? self::tenant()[$guard] ?? [];
    }

    /**
     * Internal permissions: they exist in the catalog (so a platform operator can grant
     * them to a specific role by hand) but are never given to system roles (store owner)
     * and are not offered in the role editors (decision #116).
     *
     * @var array<string, list<string>>
     */
    public const INTERNAL = [
        Guard::Vendor->value => ['ui-kit.view'],
    ];

    /**
     * Permissions a role editor may offer / a system role receives (catalog minus INTERNAL).
     *
     * @return list<string>
     */
    public static function assignable(string $guard): array
    {
        return array_values(array_diff(self::forGuard($guard), self::INTERNAL[$guard] ?? []));
    }

    /**
     * Permission names grouped by resource ("localization.languages" => ["view", "manage"])
     * for the role matrix.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(string $guard): array
    {
        $groups = [];

        foreach (self::forGuard($guard) as $permission) {
            $groups[Str::beforeLast($permission, '.')][] = Str::afterLast($permission, '.');
        }

        return $groups;
    }

    /**
     * Adds the "view" sibling of every "manage" permission (manage implies view).
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function withImplied(array $permissions, string $guard): array
    {
        $known = self::forGuard($guard);
        $result = $permissions;

        foreach ($permissions as $permission) {
            if (Str::endsWith($permission, '.manage')) {
                $view = Str::beforeLast($permission, '.manage').'.view';

                if (in_array($view, $known, true)) {
                    $result[] = $view;
                }
            }
        }

        return array_values(array_unique($result));
    }
}
