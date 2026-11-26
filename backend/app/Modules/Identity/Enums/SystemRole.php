<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Built-in roles that the UI locks (cannot be edited or deleted).
 */
enum SystemRole: string
{
    use HasEnumHelpers;

    case SuperAdmin = 'super-admin';

    /** Store owner role of every store database (guard "vendor", PermissionSyncService). */
    public const VENDOR_OWNER = 'owner';

    public static function isLocked(string $role): bool
    {
        return self::tryFrom($role) !== null;
    }

    /**
     * Locked per guard: super-admin on the admin guard, the store "owner" role on the
     * vendor guard (VEN-077: the owner is untouchable).
     */
    public static function lockedFor(string $role, string $guard): bool
    {
        return match ($guard) {
            Guard::Vendor->value => $role === self::VENDOR_OWNER,
            default => self::isLocked($role),
        };
    }
}
