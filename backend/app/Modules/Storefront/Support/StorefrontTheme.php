<?php

namespace App\Modules\Storefront\Support;

/**
 * The current store's theme family and branding for the storefront shells.
 *
 * Until the Storefront appearance settings exist, the family is read from the tenant's
 * data column (`theme_family`, set at registration or by the platform admin) and
 * falls back to "ecommet". The store name / logo come from the tenant record
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
        $family = function_exists('tenant') && tenancy()->initialized ? tenant('theme_family') : null;

        return is_string($family) && in_array($family, self::FAMILIES, true) ? $family : self::DEFAULT_FAMILY;
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
