<?php

namespace App\Modules\Platform\Support;

/**
 * The platform demo store (decision #117 a, WEB-004): a normal store flagged `is_demo`
 * (tenants.data) that renders every catalogue theme for the central theme previews. It is
 * read-only: cart, checkout and account actions answer with a "demo store" notice
 * (Storefront\Http\Middleware\GuardDemoStore) and its pages are never indexed.
 *
 *   php artisan storefront:demo-store theme-demo --platform
 */
final class DemoStore
{
    public const FLAG = 'is_demo';

    public const DEFAULT_SLUG = 'theme-demo';

    /**
     * Whether the store of the current request is the platform demo store.
     */
    public static function isCurrent(): bool
    {
        return tenancy()->initialized && (bool) tenant(self::FLAG);
    }
}
