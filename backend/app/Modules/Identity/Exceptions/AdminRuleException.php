<?php

namespace App\Modules\Identity\Exceptions;

use DomainException;

/**
 * Business rules of Admins & roles (answered as 422 with the message).
 */
final class AdminRuleException extends DomainException
{
    public static function cannotDeleteSelf(): self
    {
        return new self(__('identity::admins.rules.delete_self'));
    }

    public static function cannotDeactivateSelf(): self
    {
        return new self(__('identity::admins.rules.deactivate_self'));
    }

    public static function lastSuperAdmin(): self
    {
        return new self(__('identity::admins.rules.last_super_admin'));
    }

    public static function superAdminOnly(): self
    {
        return new self(__('identity::admins.rules.super_admin_only'));
    }

    public static function roleLocked(string $guard = 'admin'): self
    {
        return new self(__($guard === 'vendor' ? 'identity::roles.rules.locked_owner' : 'identity::roles.rules.locked'));
    }

    public static function roleInUse(int $count, string $guard = 'admin'): self
    {
        return new self(trans_choice($guard === 'vendor' ? 'identity::roles.rules.in_use_staff' : 'identity::roles.rules.in_use', $count, ['count' => $count]));
    }
}
