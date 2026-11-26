<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Rows of Vendor › Settings › Notifications (VEN-087, Figma Vendor Panel new screens
 * 91:119215). Every panel notification a store member receives ("<category>.<event>")
 * maps to one topic; the member's topic × channel switches decide its delivery
 * (EnforceVendorNotificationPreferences). Anything unmapped is "store_updates".
 */
enum VendorNotificationTopic: string
{
    use HasEnumHelpers;

    case NewOrder = 'new_order';
    case ReturnRequested = 'return_requested';
    case PayoutSent = 'payout_sent';
    case LowStock = 'low_stock';
    case RequestReplies = 'request_replies';
    case SettlementDue = 'settlement_due';
    case StoreUpdates = 'store_updates';

    public static function fromType(string $type): self
    {
        [$category, $event] = array_pad(explode('.', $type, 2), 2, '');

        return match (true) {
            str_contains($event, 'low_stock') || str_contains($event, 'out_of_stock') => self::LowStock,
            $category === NotificationCategory::Orders->value && (str_contains($event, 'return') || str_contains($event, 'refund')) => self::ReturnRequested,
            $category === NotificationCategory::Orders->value => self::NewOrder,
            $category === NotificationCategory::Payments->value && str_contains($event, 'payout') => self::PayoutSent,
            $category === NotificationCategory::Payments->value => self::SettlementDue,
            $category === NotificationCategory::Requests->value || $category === NotificationCategory::Support->value => self::RequestReplies,
            default => self::StoreUpdates,
        };
    }

    /**
     * Default switch when the member has not chosen yet: everything on, except e-mail
     * for the high-volume stock alerts (Figma defaults).
     */
    public function defaultEnabled(NotificationChannel $channel): bool
    {
        return ! ($this === self::LowStock && $channel === NotificationChannel::Email);
    }
}
