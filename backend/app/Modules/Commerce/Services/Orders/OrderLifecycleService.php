<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Events\OrderCancelled;
use App\Modules\Commerce\Events\OrderDelivered;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\OrderShipped;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Exceptions\OrderTransitionException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Promotions\CouponCatalog;
use App\Modules\Commerce\Services\Promotions\CouponEligibilityService;
use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Commerce\Services\Stock\StockReservationService;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;

/**
 * The order state machine (OrderStatus::transitions()) and its side effects:
 *   paid      → stock holds committed, coupon redemption recorded (only now: a coupon is
 *               used once the order succeeds), OrderPaid;
 *   cod       → same as paid but the order goes straight to processing (paid on delivery);
 *   cancelled → stock holds released, OrderCancelled;
 *   shipped / delivered → timestamps, OrderShipped / OrderDelivered.
 * Every change is written to order_status_history and fires OrderStatusChanged.
 */
class OrderLifecycleService extends BaseService
{
    public function __construct(
        private readonly OrderRepositoryContract $orders,
        private readonly StockReservationService $stock,
        private readonly CouponCatalog $coupons,
        private readonly CouponEligibilityService $eligibility,
    ) {}

    /**
     * Payment captured for a checkout group: idempotent (already paid orders are skipped).
     *
     * @return Collection<int, Order> the orders that moved to paid
     */
    public function markGroupPaid(string $checkoutGroup, string $gateway, ?string $note = null): Collection
    {
        return $this->settleGroup($checkoutGroup, OrderStatus::Paid, PaymentStatus::Paid, $gateway, ActorType::Gateway, $note);
    }

    /**
     * Cash on delivery confirmed (manual / admin orders): processing, paid on delivery.
     *
     * @return Collection<int, Order>
     */
    public function confirmCashOnDelivery(string $checkoutGroup, ActorType $actor = ActorType::System, ?int $actorId = null): Collection
    {
        return $this->settleGroup($checkoutGroup, OrderStatus::Processing, PaymentStatus::CashOnDelivery, 'cod', $actor, null, $actorId);
    }

    public function markPaymentFailed(string $checkoutGroup): void
    {
        foreach ($this->orders->forGroup($checkoutGroup) as $order) {
            if ($order->status === OrderStatus::PendingPayment) {
                $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
            }
        }
    }

    public function transition(Order $order, OrderStatus $to, ActorType $actor = ActorType::System, ?int $actorId = null, ?string $note = null): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new OrderTransitionException($from, $to);
        }

        $this->transaction(function () use ($order, $from, $to, $actor, $actorId, $note): void {
            $order->forceFill(['status' => $to] + $this->timestamps($to))->save();
            $this->orders->recordHistory($order, $from, $to, $actor, $actorId, $note);

            if ($to === OrderStatus::Cancelled) {
                $order->forceFill(['cancel_reason' => $note, 'reservation_expires_at' => null])->save();
                $this->stock->release($order->checkout_group, $order->uuid);
            }

            if ($to === OrderStatus::Refunded) {
                $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
            }
        });

        $this->announce($order, $from, $to);

        return $order;
    }

    public function ship(Order $order, ?string $trackingNumber = null, ActorType $actor = ActorType::Vendor, ?int $actorId = null): Order
    {
        if ($trackingNumber !== null) {
            $order->forceFill(['tracking_number' => $trackingNumber]);
        }

        return $this->transition($order, OrderStatus::Shipped, $actor, $actorId);
    }

    public function cancel(Order $order, ActorType $actor, ?int $actorId = null, ?string $reason = null): Order
    {
        return $this->transition($order, OrderStatus::Cancelled, $actor, $actorId, $reason);
    }

    /**
     * Customer cancellation from the account (before shipping).
     */
    public function cancelByCustomer(Order $order, int $customerId, ?string $reason = null): Order
    {
        if ($order->customer_id !== $customerId) {
            throw CommerceException::rule('order_not_found', 404);
        }

        if (! $order->status->isCancellableByCustomer()) {
            throw CommerceException::rule('not_cancellable');
        }

        return $this->cancel($order, ActorType::Customer, $customerId, $reason);
    }

    /**
     * Unpaid group past its reservation deadline: cancelled, holds released.
     */
    public function expireGroup(string $checkoutGroup): int
    {
        $expired = 0;

        foreach ($this->orders->forGroup($checkoutGroup) as $order) {
            if ($order->status === OrderStatus::PendingPayment && $order->reservation_expires_at?->isPast()) {
                $this->cancel($order, ActorType::System, null, (string) __('commerce::orders.notes.payment_timeout'));
                $expired++;
            }
        }

        $this->stock->release($checkoutGroup);

        return $expired;
    }

    /**
     * @return Collection<int, Order>
     */
    private function settleGroup(string $checkoutGroup, OrderStatus $to, PaymentStatus $payment, string $gateway, ActorType $actor, ?string $note, ?int $actorId = null): Collection
    {
        $orders = $this->orders->forGroup($checkoutGroup);
        $moved = new Collection;

        if ($orders->isEmpty()) {
            return $moved;
        }

        $this->transaction(function () use ($orders, $to, $payment, $gateway, $actor, $actorId, $note, $moved): void {
            foreach ($orders as $order) {
                if ($order->status !== OrderStatus::PendingPayment) {
                    continue;
                }

                $order->forceFill(['status' => $to, 'payment_status' => $payment, 'payment_gateway' => $gateway, 'paid_at' => $payment === PaymentStatus::Paid ? now() : null, 'reservation_expires_at' => null])->save();
                $this->orders->recordHistory($order, OrderStatus::PendingPayment, $to, $actor, $actorId, $note);
                $moved->push($order);
            }
        });

        if ($moved->isEmpty()) {
            return $moved;
        }

        $this->stock->commit($checkoutGroup);
        $this->redeemCoupon($moved);

        foreach ($moved as $order) {
            $this->announce($order, OrderStatus::PendingPayment, $to);
            OrderPaid::dispatch($order, $this->tenantId());
        }

        return $moved;
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function redeemCoupon(Collection $orders): void
    {
        /** @var Order|null $withCoupon */
        $withCoupon = $orders->first(static fn (Order $order): bool => $order->coupon_code !== null && $order->coupon_id !== null);

        if ($withCoupon === null) {
            return;
        }

        $coupon = $this->coupons->findByCode((string) $withCoupon->coupon_code);

        if ($coupon === null || $coupon->id !== $withCoupon->coupon_id || $coupon->source !== $withCoupon->coupon_source) {
            return;
        }

        $discount = Money::ofMinor((int) $orders->sum('coupon_discount'));
        $this->eligibility->recordRedemption($coupon, $withCoupon->customer_id, $withCoupon->checkout_group, $discount);
    }

    private function announce(Order $order, ?OrderStatus $from, OrderStatus $to): void
    {
        $tenantId = $this->tenantId();

        OrderStatusChanged::dispatch($order, $tenantId, $from, $to);

        match ($to) {
            OrderStatus::Cancelled => OrderCancelled::dispatch($order, $tenantId),
            OrderStatus::Shipped => OrderShipped::dispatch($order, $tenantId),
            OrderStatus::Delivered => OrderDelivered::dispatch($order, $tenantId),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function timestamps(OrderStatus $to): array
    {
        $column = match ($to) {
            OrderStatus::Paid => 'paid_at',
            OrderStatus::Shipped => 'shipped_at',
            OrderStatus::Delivered => 'delivered_at',
            OrderStatus::Completed => 'completed_at',
            OrderStatus::Cancelled => 'cancelled_at',
            OrderStatus::Refunded => 'refunded_at',
            OrderStatus::Returned => 'returned_at',
            default => null,
        };

        return $column !== null ? [$column => now()] : [];
    }

    private function tenantId(): int
    {
        return (int) StoreContext::tenant()->getTenantKey();
    }
}
