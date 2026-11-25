<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\Guard;

/**
 * Single source of permission names per guard. Modules add their permissions here
 * (or via their own seeders) as they are built. Convention: "<resource>.<ability>",
 * where "manage" implies "view".
 */
final class PermissionCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public static function central(): array
    {
        return [
            Guard::Admin->value => ['dashboard.view', 'horizon.view', 'tenants.impersonate', 'system.ui-kit.view'],
            Guard::Affiliate->value => ['dashboard.view'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function tenant(): array
    {
        return [
            Guard::Vendor->value => ['dashboard.view'],
        ];
    }
}
