<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Contracts\OrderRefundLedger;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Pricing\OrderSettlement;
use App\Modules\Commerce\Pricing\RefundableOrder;
use App\Modules\Commerce\Pricing\RefundBreakdown;
use App\Modules\Commerce\Pricing\RefundCalculator;
use App\Modules\Commerce\Pricing\SettlementFigures;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Shared\Money\Money;

/**
 * The money views of one order, all computed by Commerce\Pricing (never re-implemented):
 * refundable cap (grand_total − refunded − open refunds), cancellation refund preview and
 * the settlement figures shown in both panels.
 */
class OrderFinanceService
{
    public function __construct(
        private readonly OrderRefundLedger $ledger,
        private readonly OrderSettlementRepositoryContract $settlements,
    ) {}

    public function refundable(Order $order, int $tenantId): RefundableOrder
    {
        return new RefundableOrder(
            grandTotal: $order->money('grand_total'),
            itemsGrandTotal: $order->money('items_grand_total'),
            couponDiscount: $order->money('coupon_discount'),
            shippingCharge: $order->money('shipping_charge'),
            alreadyRefunded: $this->ledger->reserved($tenantId, $order->uuid),
            lineCount: $order->relationLoaded('items') ? $order->items->count() : $order->items()->count(),
        );
    }

    /**
     * Refund a cancellation would issue (null when no money was taken online).
     */
    public function cancellationPreview(Order $order, int $tenantId): ?RefundBreakdown
    {
        if (! $order->isPaidOnline()) {
            return null;
        }

        $breakdown = RefundCalculator::forCancellation($this->refundable($order, $tenantId));

        return $breakdown->amount->isPositive() ? $breakdown : null;
    }

    /**
     * What may still be refunded: online payments, or cash collected on delivery.
     */
    public function remaining(Order $order, int $tenantId): Money
    {
        $codCollected = $order->payment_status === PaymentStatus::CashOnDelivery
            && in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true);

        return $order->isPaidOnline() || $codCollected ? $this->refundable($order, $tenantId)->remaining() : Money::zero();
    }

    public function figures(Order $order, int $tenantId): SettlementFigures
    {
        return OrderSettlement::figures(
            grandTotal: $order->money('grand_total'),
            platformProfit: $order->money('platform_profit'),
            vendorProfit: $order->money('vendor_profit'),
            refunded: $this->ledger->refunded($tenantId, $order->uuid),
            settlementsPaid: Money::ofMinor($this->settlements->paidTotal($tenantId, $order->uuid)),
            collectedBy: $order->collectedBy(),
            voidStatus: in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::PendingPayment], true),
        );
    }
}
