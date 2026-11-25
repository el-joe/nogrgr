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
                // Platform: vendors, registrations, domains, compliance, change requests
                'vendors.view', 'vendors.manage', 'vendors.delete', 'vendors.export',
                'vendors.kyc.view', 'vendors.kyc.manage',
                'vendors.domains.view', 'vendors.domains.manage',
                'requests.tenant-changes.view', 'requests.tenant-changes.manage',
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
            ],
            Guard::Affiliate->value => ['dashboard.view', 'notifications.view'],
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
                'notifications.view',
                'settings.languages.view', 'settings.languages.manage',
                'settings.countries.view', 'settings.countries.manage',
                'settings.domains.view', 'settings.domains.manage',
                'settings.compliance.view', 'settings.compliance.manage',
                'settings.payment-gateways.view', 'settings.payment-gateways.manage',
                'settings.email-templates.view', 'settings.email-templates.manage',
                'requests.tenant-changes.view', 'requests.tenant-changes.manage',
                'finance.subscription.view',
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
