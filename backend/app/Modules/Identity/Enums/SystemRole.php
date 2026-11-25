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

    public static function isLocked(string $role): bool
    {
        return self::tryFrom($role) !== null;
    }
}
