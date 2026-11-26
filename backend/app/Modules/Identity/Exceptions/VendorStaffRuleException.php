<?php

namespace App\Modules\Identity\Exceptions;

use DomainException;

/**
 * Business rules of the store team (VEN-076): the owner is untouchable, nobody acts on
 * their own account here, the plan's staff quota, invites only for pending members.
 */
final class VendorStaffRuleException extends DomainException
{
    public static function ownerLocked(): self
    {
        return new self(__('identity::staff.rules.owner_locked'));
    }

    public static function self(): self
    {
        return new self(__('identity::staff.rules.self'));
    }

    public static function limitReached(int $limit): self
    {
        return new self(trans_choice('identity::staff.rules.limit', $limit, ['count' => $limit]));
    }

    public static function notPending(): self
    {
        return new self(__('identity::staff.rules.not_pending'));
    }

    public static function pending(): self
    {
        return new self(__('identity::staff.rules.pending'));
    }

    public static function ownerRole(): self
    {
        return new self(__('identity::staff.rules.owner_role'));
    }
}
