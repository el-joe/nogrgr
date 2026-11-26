<?php

namespace App\Modules\Storefront\Services\Checkout;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Services\Payments\PaymentService;
use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Storefront\Enums\PaymentReturnOutcome;
use App\Modules\Storefront\Services\StorefrontContext;

/**
 * Theme side of a storefront payment (SF-ECO-023b): the order group a customer pays
 * (pay page / retry), and the gateway return `checkout/payment/{gateway}/{order}`
 * (`?status=success|cancel&reference=`, built by Commerce PaymentService::start) settled
 * through PaymentService::confirm — never re-implemented here.
 */
class PaymentFlow
{
    public function __construct(
        private readonly CustomerOrderService $orders,
        private readonly OrderRepositoryContract $repository,
        private readonly PaymentService $payments,
        private readonly StorefrontGatewayService $gateways,
        private readonly StorefrontContext $context,
        private readonly MoneyPresenter $money,
    ) {}

    /**
     * The customer's order or a 404 (another customer's / unknown uuid).
     */
    public function order(Customer $customer, string $uuid): Order
    {
        try {
            return $this->orders->find($customer, $uuid);
        } catch (CommerceException) {
            abort(404);
        }
    }

    public function payable(Order $order): bool
    {
        return $order->status === OrderStatus::PendingPayment && $order->payment_status !== PaymentStatus::Paid;
    }

    /**
     * Pay page data: order number(s), the group total (one payment pays the group, in
     * USD; shown in the display currency) and the payment methods (spec 08 list).
     *
     * @return array<string, mixed>
     */
    public function payPage(Order $order): array
    {
        $group = $this->repository->forGroup($order->checkout_group)
            ->filter(static fn (Order $member): bool => $member->status === OrderStatus::PendingPayment);
        $this->money->useDisplayCurrency($this->context->currencyCode());

        return [
            'order' => $order,
            'numbers' => $group->pluck('number')->implode(', ') ?: $order->number,
            'total' => $this->money->present((int) ($group->sum('grand_total') ?: $order->grand_total)),
            'gateways' => array_map(static fn ($option): array => $option->toArray(), $this->gateways->options()),
        ];
    }

    public function gatewayListed(string $code): bool
    {
        $gateway = PaymentGatewayCode::tryFrom($code);

        return $gateway !== null && $this->gateways->find($gateway) !== null;
    }

    public function confirm(Order $order, string $status, string $reference): PaymentReturnOutcome
    {
        if ($status === 'cancel') {
            return PaymentReturnOutcome::Cancelled;
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return PaymentReturnOutcome::Paid;
        }

        if ($reference === '') {
            return PaymentReturnOutcome::Failed;
        }

        try {
            $payment = $this->payments->confirm($order, $reference);
        } catch (CommerceException) {
            return PaymentReturnOutcome::Failed;
        }

        return match ($payment->status) {
            PaymentAttemptStatus::Paid => PaymentReturnOutcome::Paid,
            PaymentAttemptStatus::Pending, PaymentAttemptStatus::Initiated => PaymentReturnOutcome::Pending,
            PaymentAttemptStatus::Cancelled => PaymentReturnOutcome::Cancelled,
            PaymentAttemptStatus::Failed => PaymentReturnOutcome::Failed,
        };
    }
}
