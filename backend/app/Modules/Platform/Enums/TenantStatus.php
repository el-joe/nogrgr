<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Store lifecycle:
 *   pending → provisioning → active | failed · failed → provisioning (retry)
 *   active ↔ suspended. Trial / cancelled are legacy values kept readable.
 * Billing state (trial, past due…) lives on the subscription, not here.
 */
enum TenantStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';
    case Suspended = 'suspended';
    case Trial = 'trial';
    case Cancelled = 'cancelled';

    /**
     * Statuses an admin filters by (legacy values excluded).
     *
     * @return list<self>
     */
    public static function listed(): array
    {
        return [self::Pending, self::Provisioning, self::Active, self::Failed, self::Suspended];
    }

    /**
     * @return array<string, string>
     */
    public static function listedOptions(): array
    {
        $options = [];

        foreach (self::listed() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Provisioning, self::Failed],
            self::Provisioning => [self::Active, self::Failed],
            self::Failed => [self::Provisioning],
            self::Active, self::Trial => [self::Suspended],
            self::Suspended, self::Cancelled => [self::Active],
        }, true);
    }

    public function canRetryProvisioning(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::Active, self::Trial], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Trial, self::Provisioning => 'info',
            self::Pending => 'warning',
            self::Failed, self::Suspended => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
