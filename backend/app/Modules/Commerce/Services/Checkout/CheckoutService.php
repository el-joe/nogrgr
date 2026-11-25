<?php

namespace App\Modules\Commerce\Services\Checkout;

use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\DTOs\Checkout\CheckoutOptions;
use App\Modules\Commerce\DTOs\Checkout\CheckoutSelectionData;
use App\Modules\Commerce\DTOs\Checkout\PlaceOrderData;
use App\Modules\Commerce\DTOs\Payments\GatewayOption;
use App\Modules\Commerce\Enums\CheckoutAttemptStatus;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CheckoutAttempt;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\CheckoutAttemptRepositoryContract;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Identity\DTOs\CheckoutAddressSelectionData;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Checkout (signed-in customers, like the old storefront):
 *  - options(): saved addresses (spec 06 §2 selection), the cart summary priced for the
 *    shipping address country, the payment methods (spec 08 filtering) and the first
 *    reason the pay button is disabled;
 *  - place(): idempotent by key (a repeated submit returns the first result, a
 *    concurrent one is refused), guards in the old order (empty cart → address → items →
 *    shipping → coupon → gateway still listed), stock reserved, order(s) written (split
 *    like OrderComposer), cart cleared. Payment starts with PaymentService::start().
 */
class CheckoutService extends BaseService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CustomerAddressService $addresses,
        private readonly StorefrontGatewayService $gateways,
        private readonly OrderPlacementService $placement,
        private readonly CheckoutAttemptRepositoryContract $attempts,
    ) {}

    public function options(CartContextData $context, CheckoutSelectionData $selection): CheckoutOptions
    {
        $customer = $this->customer($context);
        $addresses = $this->addresses->forCheckout($customer, $selection->shippingAddressId, $selection->billingAddressId, $selection->billingSameAsShipping);
        $cart = $this->carts->current($context);
        $summary = $this->summary($cart, $customer, $addresses);
        $gateways = $this->gateways->options();

        return new CheckoutOptions($addresses, $summary, $gateways, $this->block($summary, $addresses, $gateways !== []));
    }

    /**
     * @return array<string, mixed> the stored response (same for every replay of the key)
     */
    public function place(CartContextData $context, PlaceOrderData $data): array
    {
        $customer = $this->customer($context);
        $existing = $this->attempts->findByKey($data->idempotencyKey);

        if ($existing !== null) {
            return $this->replay($existing, $customer, $data);
        }

        $attempt = $this->attempts->claim([
            'idempotency_key' => $data->idempotencyKey,
            'customer_id' => $customer->id,
            'request_hash' => $data->hash(),
            'status' => CheckoutAttemptStatus::Processing,
        ]);

        if ($attempt === null) {
            return $this->replay($this->attempts->findByKey($data->idempotencyKey) ?? throw CommerceException::rule('checkout_in_progress', 409), $customer, $data);
        }

        try {
            $response = $this->placeOrders($context, $customer, $data);
        } catch (Throwable $exception) {
            $this->attempts->delete($attempt);

            throw $exception;
        }

        $this->attempts->update($attempt, [
            'status' => CheckoutAttemptStatus::Completed,
            'checkout_group' => $response['checkout_group'],
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function placeOrders(CartContextData $context, Customer $customer, PlaceOrderData $data): array
    {
        $cart = $this->carts->current($context);

        if ($cart === null || $cart->items()->doesntExist()) {
            throw CommerceException::blocked(CheckoutBlock::EmptyCart);
        }

        $addresses = $this->addresses->forCheckout($customer, $data->shippingAddressId, $data->billingAddressId, $data->billingSameAsShipping);
        $summary = $this->summary($cart, $customer, $addresses);
        $gateway = $this->gateways->find($data->gateway);
        $block = $this->block($summary, $addresses, $gateway !== null);

        if ($block === CheckoutBlock::NoGateway) {
            $block = CheckoutBlock::GatewayUnavailable;
        }

        if ($block !== null || ! $gateway instanceof GatewayOption) {
            throw CommerceException::blocked($block ?? CheckoutBlock::GatewayUnavailable, $block === CheckoutBlock::ShippingUnavailable ? ['shipping' => $summary->composed->shipping->toArray()] : null);
        }

        $orders = $this->placement->place($summary, $addresses, $gateway);
        $this->carts->clear($cart);

        return $this->response($orders, $gateway);
    }

    /**
     * Pay-button guard order (spec 08 §3): empty cart → address → items → shipping →
     * coupon → gateway.
     */
    public function block(CartSummary $summary, CheckoutAddressSelectionData $addresses, bool $hasGateway): ?CheckoutBlock
    {
        return match (true) {
            $summary->isEmpty() => CheckoutBlock::EmptyCart,
            ! $addresses->hasAddress() => CheckoutBlock::NoAddress,
            $summary->hasIssues() => CheckoutBlock::UnavailableItems,
            ! $summary->composed->canCheckout() => CheckoutBlock::ShippingUnavailable,
            $summary->couponRejected() => CheckoutBlock::CouponInvalid,
            ! $hasGateway => CheckoutBlock::NoGateway,
            default => null,
        };
    }

    private function summary(?Cart $cart, Customer $customer, CheckoutAddressSelectionData $addresses): CartSummary
    {
        $countryId = $addresses->shipping?->country_id;

        if ($cart === null) {
            return $this->carts->summary(new CartContextData(customer: $customer));
        }

        return $this->carts->summaryOf($cart, $customer, $countryId);
    }

    /**
     * @return array<string, mixed>
     */
    private function replay(CheckoutAttempt $attempt, Customer $customer, PlaceOrderData $data): array
    {
        if ($attempt->customer_id !== $customer->id || $attempt->request_hash !== $data->hash()) {
            throw CommerceException::rule('idempotency_mismatch', 409);
        }

        if ($attempt->status !== CheckoutAttemptStatus::Completed || $attempt->response === null) {
            throw CommerceException::rule('checkout_in_progress', 409);
        }

        return array_merge($attempt->response, ['replayed' => true]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    private function response(Collection $orders, GatewayOption $gateway): array
    {
        /** @var Order $first */
        $first = $orders->first();

        return [
            'checkout_group' => $first->checkout_group,
            'gateway' => $gateway->code->value,
            'grand_total' => (int) $orders->sum('grand_total'),
            'orders' => $orders->map(static fn (Order $order): array => [
                'uuid' => $order->uuid,
                'number' => $order->number,
                'kind' => $order->kind->value,
                'grand_total' => $order->grand_total,
            ])->values()->all(),
            'payment_order' => $first->uuid,
            'replayed' => false,
        ];
    }

    private function customer(CartContextData $context): Customer
    {
        return $context->customer ?? throw CommerceException::blocked(CheckoutBlock::SignInRequired);
    }
}
