<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\Guard;
use Illuminate\Support\Str;

/**
 * Role editor matrix (ADM-111a, Figma Admin Panel 80:91028): PermissionCatalog
 * resources grouped by module, one column per ability. Resource → module is decided
 * by the longest matching prefix in MODULES; unknown resources land in "other".
 */
final class PermissionMatrix
{
    /**
     * Module key => resource prefixes, in display order.
     *
     * @var array<string, list<string>>
     */
    private const MODULES = [
        'overview' => ['dashboard', 'reports'],
        'catalog' => ['catalog'],
        'requests' => ['requests'],
        'vendors' => ['vendors', 'tenants'],
        'billing' => ['plans', 'subscriptions', 'finance'],
        'affiliates' => ['affiliates'],
        'localization' => ['localization'],
        'content' => ['email-templates', 'notifications', 'content'],
        'integrations' => ['integrations'],
        'identity' => ['admins', 'roles', 'audit'],
        'system' => ['system', 'horizon', 'settings', 'support'],
    ];

    /**
     * Vendor (store) panel grouping (VEN-077a, Figma 90:115822), in sidebar order.
     *
     * @var array<string, list<string>>
     */
    private const VENDOR_MODULES = [
        'overview' => ['dashboard', 'onboarding', 'notifications', 'analytics', 'support'],
        'catalog' => ['catalog'],
        'sales' => ['orders', 'returns', 'refunds'],
        'customers' => ['customers', 'subscribers'],
        'store' => ['store'],
        'marketing' => ['coupons', 'flash-sales', 'marketing'],
        'requests' => ['requests'],
        'billing' => ['finance', 'partner'],
        'settings' => ['settings'],
    ];

    /**
     * Ability column order; abilities not listed follow alphabetically.
     *
     * @var list<string>
     */
    private const ABILITIES = ['view', 'manage', 'delete', 'export', 'impersonate'];

    /**
     * modules: module => resource => ability => permission name.
     *
     * @return array{abilities: list<string>, modules: array<string, array<string, array<string, string>>>}
     */
    public static function for(string $guard): array
    {
        $map = self::modulesFor($guard);
        $modules = array_fill_keys([...array_keys($map), 'other'], []);
        $abilities = [];

        foreach (PermissionCatalog::assignable($guard) as $permission) {
            $resource = Str::beforeLast($permission, '.');
            $ability = Str::afterLast($permission, '.');
            $modules[self::moduleOf($resource, $guard)][$resource][$ability] = $permission;
            $abilities[$ability] = true;
        }

        $ordered = array_values(array_intersect(self::ABILITIES, array_keys($abilities)));
        $extra = array_values(array_diff(array_keys($abilities), self::ABILITIES));
        sort($extra);

        return [
            'abilities' => [...$ordered, ...$extra],
            'modules' => array_filter($modules),
        ];
    }

    public static function moduleOf(string $resource, string $guard = 'admin'): string
    {
        $best = 'other';
        $length = 0;

        foreach (self::modulesFor($guard) as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (($resource === $prefix || Str::startsWith($resource, $prefix.'.')) && strlen($prefix) > $length) {
                    $best = $module;
                    $length = strlen($prefix);
                }
            }
        }

        return $best;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function modulesFor(string $guard): array
    {
        return $guard === Guard::Vendor->value ? self::VENDOR_MODULES : self::MODULES;
    }

    /**
     * Translated row label of a resource ("localization.languages" → "Languages").
     */
    public static function resourceLabel(string $resource): string
    {
        $key = 'identity::roles.resources.'.$resource;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline(str_replace('.', ' ', $resource));
    }

    public static function moduleLabel(string $module): string
    {
        $key = 'identity::roles.modules.'.$module;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($module);
    }

    public static function abilityLabel(string $ability): string
    {
        $key = 'identity::roles.abilities.'.$ability;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($ability);
    }
}
