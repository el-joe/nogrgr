<?php

namespace App\Modules\AfterSales\Support;

use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Commerce\Enums\CollectedBy;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Models\Order;

/**
 * Who may do what on returns / refunds (decision #71), on top of the route permissions:
 *   platform staff: every transition the state machine allows, except on own-products
 *   orders (merchant fulfilled → read-only for the platform); any open refund;
 *   store staff: review steps (approve, reject, ask info, resume, close) on every return;
 *   physical steps (received, inspect, exchange) only on own-products orders; refunds only
 *   when the store collected the money (own gateway / cash on delivery).
 */
final class AfterSalesAccess
{
    private const VENDOR_REVIEW = [ReturnAction::Approve, ReturnAction::Reject, ReturnAction::RequestInfo, ReturnAction::Resume, ReturnAction::Close];

    /**
     * @return list<ReturnAction>
     */
    public static function adminActions(ReturnRequest $return, Order $order): array
    {
        return $order->isOwnProducts() ? [] : ReturnAction::availableFor($return->status, $return->type);
    }

    /**
     * @return list<ReturnAction>
     */
    public static function vendorActions(ReturnRequest $return, Order $order): array
    {
        return array_values(array_filter(ReturnAction::availableFor($return->status, $return->type), static function (ReturnAction $action) use ($order): bool {
            if (in_array($action, self::VENDOR_REVIEW, true)) {
                return true;
            }

            if ($action === ReturnAction::Refund) {
                return $order->collectedBy() === CollectedBy::Vendor;
            }

            return $order->isOwnProducts();
        }));
    }

    public static function adminCanCreate(Order $order): bool
    {
        return ! $order->isOwnProducts();
    }

    public static function vendorCanRefundOrder(Order $order): bool
    {
        return $order->collectedBy() === CollectedBy::Vendor;
    }

    public static function vendorCanActOnRefund(Refund $refund): bool
    {
        return $refund->status->isOpen() && $refund->payment_source !== PaymentSource::Central;
    }

    public static function adminCanActOnRefund(Refund $refund): bool
    {
        return $refund->status->isOpen();
    }

    /**
     * Refund modal actions available for an open refund.
     *
     * @return list<string>
     */
    public static function refundActions(Refund $refund): array
    {
        if (! $refund->status->isOpen()) {
            return [];
        }

        $actions = ['complete', 'reject'];

        if (in_array($refund->status, [RefundStatus::Pending, RefundStatus::Failed], true) && $refund->method->value === 'original_payment') {
            array_unshift($actions, 'retry');
        }

        return $actions;
    }
}
