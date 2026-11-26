<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Storefront\Services\StorefrontContext;

/**
 * The current store's theme family and branding for the storefront shells.
 *
 * The family comes from the store's active theme for the shopping country
 * (StorefrontContext → StoreThemeService; registration `theme_family`, then Ecommet). The store name / logo come from the tenant record
 * (`name`, optional `logo_url`); the vendor appearance page will own them later.
 */
final class StorefrontTheme
{
    /**
     * @var list<string>
     */
    public const FAMILIES = ['ecommet', 'elora', 'souqify'];

    public const DEFAULT_FAMILY = 'ecommet';

    public static function family(): string
    {
        if (! tenancy()->initialized) {
            return self::DEFAULT_FAMILY;
        }

        $family = app(StorefrontContext::class)->family();

        return in_array($family, self::FAMILIES, true) ? $family : self::DEFAULT_FAMILY;
    }

    public static function storeName(): string
    {
        $name = tenancy()->initialized ? tenant('name') : null;

        return is_string($name) && $name !== '' ? $name : (string) config('app.name');
    }

    public static function logoUrl(): ?string
    {
        $logo = tenancy()->initialized ? tenant('logo_url') : null;

        return is_string($logo) && $logo !== '' ? $logo : null;
    }
}
