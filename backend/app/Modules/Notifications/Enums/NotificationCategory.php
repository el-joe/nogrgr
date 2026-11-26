<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Admin panel notification categories (inbox filter chips and preference rows,
 * Figma Admin Panel 80:96878). A notification type is "<category>.<event>".
 */
enum NotificationCategory: string
{
    use HasEnumHelpers;

    case Orders = 'orders';
    case Vendors = 'vendors';
    case Payments = 'payments';
    case Requests = 'requests';
    case System = 'system';
    case Support = 'support';

    public function icon(): string
    {
        return match ($this) {
            self::Orders => 'cart',
            self::Vendors => 'store',
            self::Payments => 'credit-card',
            self::Requests => 'message',
            self::System => 'settings',
            self::Support => 'lifebuoy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Orders => 'brand',
            self::Vendors => 'info',
            self::Payments => 'success',
            self::Requests => 'warning',
            self::System => 'info',
            self::Support => 'brand',
        };
    }

    /**
     * Default switch of a channel when the user has not chosen yet.
     */
    public function defaultEnabled(NotificationChannel $channel): bool
    {
        return true;
    }

    public static function fromType(string $type): ?self
    {
        return self::tryFrom(strtok($type, '.') ?: '');
    }
}
