<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a store looks like on the owner's My stores card (OWN-001): combines the store
 * lifecycle (TenantStatus) with the billing state of its current subscription.
 */
enum OwnerStoreState: string
{
    use HasEnumHelpers;

    case Live = 'live';
    case Trial = 'trial';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case NotLaunched = 'not_launched';

    public function tone(): string
    {
        return match ($this) {
            self::Live => 'success',
            self::Trial, self::PastDue => 'warning',
            self::Suspended => 'danger',
            self::NotLaunched => 'neutral',
        };
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Trial, self::PastDue, self::Suspended], true);
    }

    /**
     * The store panel can be opened (SSO) only for an active store.
     */
    public function canOpenPanel(): bool
    {
        return in_array($this, [self::Live, self::Trial, self::PastDue], true);
    }
}
