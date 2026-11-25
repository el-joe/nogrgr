<?php

namespace App\Modules\Commerce\Services\Payments;

use App\Modules\Commerce\DTOs\Payments\StartedPayment;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\PaymentResult;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Storefront payments with the store's gateway (own keys or the central fallback):
 *  - start(): one attempt per call (order_payments = the payment log), charging every
 *    order of the checkout group at once in USD; redirect gateways return a URL, Stripe
 *    (PaymentIntents) the client secret for the inline card panel;
 *  - confirm(): return-URL / polling check with the gateway (captures PayPal);
 *  - webhook(): signature-checked by the driver, replay-safe (gateway + event id stored once);
 *  - settle(): idempotent under a row lock — a capture marks the group paid exactly once
 *    (amount checked), a failure is logged and the orders stay payable until the
 *    reservation times out.
 */
class PaymentService extends BaseService
{
    public function __construct(
        private readonly StorefrontGatewayService $gateways,
        private readonly OrderRepositoryContract $orders,
        private readonly OrderPaymentRepositoryContract $payments,
        private readonly OrderLifecycleService $lifecycle,
    ) {}

    public function start(Order $order, PaymentGatewayCode $code, string $baseUrl): StartedPayment
    {
        $group = $this->orders->forGroup($order->checkout_group)->filter(static fn (Order $member): bool => $member->status === OrderStatus::PendingPayment);

        if ($group->isEmpty() || $order->payment_status === PaymentStatus::Paid) {
            throw CommerceException::rule('not_payable');
        }

        $gateway = $this->gateways->find($code) ?? throw CommerceException::blocked(CheckoutBlock::GatewayUnavailable);
        $amount = Money::ofMinor((int) $group->sum('grand_total'));

        /** @var OrderPayment $payment */
        $payment = $this->payments->create([
            'reference' => 'pay_'.Str::lower(Str::random(24)),
            'checkout_group' => $order->checkout_group,
            'gateway' => $code,
            'source' => $gateway->source,
            'mode' => $gateway->mode,
            'status' => PaymentAttemptStatus::Initiated,
            'amount' => $amount->minor,
            'currency' => $amount->currency,
        ]);

        $return = rtrim($baseUrl, '/').'/checkout/payment/'.$code->value.'/'.$order->uuid;

        try {
            $checkout = $this->gateways->driver($code)->createCheckout($gateway->config, new CheckoutRequestData(
                amount: $amount,
                reference: $payment->reference,
                description: (string) __('commerce::orders.payment_description', ['numbers' => $group->pluck('number')->implode(', ')]),
                successUrl: $return.'?status=success&reference='.$payment->reference,
                cancelUrl: $return.'?status=cancel&reference='.$payment->reference,
                customerEmail: $order->customer_email,
                locale: app()->getLocale(),
            ));
        } catch (PaymentGatewayException $exception) {
            $this->payments->update($payment, ['status' => PaymentAttemptStatus::Failed, 'failure_reason' => mb_substr($exception->detail ?? $exception->reason, 0, 500), 'completed_at' => now()]);

            throw CommerceException::blocked(CheckoutBlock::GatewayUnavailable);
        }

        $this->payments->update($payment, [
            'status' => PaymentAttemptStatus::Pending,
            'checkout_id' => $checkout->id,
            'redirect_url' => $checkout->redirectUrl !== '' ? $checkout->redirectUrl : null,
        ]);

        foreach ($group as $member) {
            $member->forceFill(['payment_gateway' => $code->value, 'payment_source' => $gateway->source, 'payment_status' => PaymentStatus::Pending])->save();
        }

        return new StartedPayment($payment, $checkout->redirectUrl !== '' ? $checkout->redirectUrl : null, $checkout->clientSecret);
    }

    /**
     * The payer came back (or the app polls): ask the gateway and settle.
     */
    public function confirm(Order $order, string $reference): OrderPayment
    {
        $payment = $this->payments->findByReference($reference);

        if ($payment === null || $payment->checkout_group !== $order->checkout_group) {
            throw CommerceException::rule('payment_not_found', 404);
        }

        if ($payment->status->isFinal() || $payment->checkout_id === null) {
            return $payment;
        }

        $config = $this->gateways->configFor($payment->gateway) ?? throw CommerceException::blocked(CheckoutBlock::GatewayUnavailable);

        try {
            $result = $this->gateways->driver($payment->gateway)->verify($config, $payment->checkout_id);
        } catch (PaymentGatewayException $exception) {
            throw CommerceException::blocked(CheckoutBlock::GatewayUnavailable);
        }

        return $this->settle($payment, $result);
    }

    /**
     * @return string processed | duplicate | ignored
     *
     * @throws PaymentGatewayException invalid signature / misconfigured webhook secret
     */
    public function webhook(PaymentGatewayCode $code, Request $request): string
    {
        $config = $this->gateways->configFor($code);

        if ($config === null || ! $this->gateways->supports($code)) {
            throw PaymentGatewayException::misconfigured();
        }

        $driver = $this->gateways->driver($code);
        $notification = $driver->parseWebhook($config, $request);

        if (! $notification->isRelevant()) {
            return 'ignored';
        }

        if (! $this->payments->rememberWebhook($code->value, $notification->eventId, $notification->type, $notification->checkoutId)) {
            return 'duplicate';
        }

        $payment = $this->payments->findByCheckoutId($code->value, (string) $notification->checkoutId);

        if ($payment === null) {
            return 'ignored';
        }

        $result = $notification->needsConfirmation
            ? $driver->verify($config, (string) $notification->checkoutId)
            : new PaymentResult($notification->outcome, (string) $notification->checkoutId, reason: $notification->reason);

        $this->settle($payment, $result);

        return 'processed';
    }

    /**
     * Applies a gateway verdict to an attempt exactly once.
     */
    public function settle(OrderPayment $payment, PaymentResult $result): OrderPayment
    {
        $paid = false;

        $payment = $this->transaction(function () use ($payment, $result, &$paid): OrderPayment {
            $locked = $this->payments->lock($payment);

            if ($locked->status->isFinal() || $result->outcome === PaymentOutcome::Pending) {
                return $locked;
            }

            $outcome = $result->outcome === PaymentOutcome::Paid && ! $this->amountMatches($locked, $result) ? PaymentOutcome::Failed : $result->outcome;
            $status = PaymentAttemptStatus::fromOutcome($outcome);

            $this->payments->update($locked, [
                'status' => $status,
                'transaction_id' => $result->transactionId ?? $locked->transaction_id,
                'failure_reason' => $outcome !== $result->outcome ? 'amount_mismatch' : ($result->reason !== null ? mb_substr($result->reason, 0, 500) : null),
                'details' => $result->details,
                'completed_at' => now(),
            ]);

            $paid = $status === PaymentAttemptStatus::Paid;

            return $locked;
        });

        if ($paid) {
            $this->lifecycle->markGroupPaid($payment->checkout_group, $payment->gateway->value, $payment->reference);
        } elseif (in_array($payment->status, [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled], true)) {
            $this->lifecycle->markPaymentFailed($payment->checkout_group);
            Log::info('Storefront payment not completed.', ['reference' => $payment->reference, 'status' => $payment->status->value]);
        }

        return $payment;
    }

    /**
     * Gateway amount (when reported) equals the attempt amount: Stripe reports minor
     * units, PayPal a decimal string.
     */
    private function amountMatches(OrderPayment $payment, PaymentResult $result): bool
    {
        $reported = $result->details['amount'] ?? null;

        return match (true) {
            is_int($reported) => $reported === $payment->amount,
            is_string($reported) && is_numeric($reported) => Money::of($reported)->minor === $payment->amount,
            default => true,
        };
    }
}
