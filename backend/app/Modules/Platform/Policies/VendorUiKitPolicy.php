<?php

namespace App\Modules\Platform\Policies;

use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Routing\VendorPanel;
use Throwable;

/**
 * Vendor UI kit (VEN-093, decision #116): internal page, never shown to store staff by
 * default. Allowed to a platform admin impersonating the store (vendor session carries
 * the impersonator id) or to a member whose role was given the internal `ui-kit.view`
 * permission by hand (PermissionCatalog::INTERNAL — never granted to the owner role).
 * Gate `vendor.ui-kit.view` (Platform ModuleServiceProvider).
 */
final class VendorUiKitPolicy
{
    public const ABILITY = 'vendor.ui-kit.view';

    public const PERMISSION = 'ui-kit.view';

    public function view(mixed $user): bool
    {
        if (! $user instanceof VendorStaff) {
            return false;
        }

        $request = request();

        if ($request->hasSession() && $request->session()->has(VendorPanel::SESSION_IMPERSONATOR)) {
            return true;
        }

        try {
            return $user->hasPermissionTo(self::PERMISSION, 'vendor');
        } catch (Throwable) {
            return false;
        }
    }
}
