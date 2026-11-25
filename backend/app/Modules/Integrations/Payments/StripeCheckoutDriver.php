<?php

namespace App\Modules\Integrations\Payments;

use App\Modules\Integrations\Contracts\PaymentGatewayDriver;
use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\HostedCheckout;
use App\Modules\Integrations\DTOs\PaymentResult;
use App\Modules\Integrations\DTOs\WebhookNotification;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (hosted page) over the REST API:
 *   createCheckout  POST /v1/checkout/sessions (mode=payment, one price_data line,
 *                   client_reference_id = our reference, Idempotency-Key)
 *   verify          GET  /v1/checkout/sessions/{id}: payment_status paid → paid,
 *                   status expired → cancelled, otherwise pending
 *   parseWebhook    Stripe-Signature `t=…,v1=…` = HMAC-SHA256("t.payload") with the
 *                   endpoint's webhook secret, 5-minute tolerance
 */
class StripeCheckoutDriver implements PaymentGatewayDriver
{
    public const BASE_URL = 'https://api.stripe.com';

    public const TIMEOUT_SECONDS = 15;

    public const SIGNATURE_TOLERANCE_SECONDS = 300;

    /** Locales Stripe Checkout understands (others fall back to auto). */
    private const LOCALES = ['ar', 'en'];

    public function createCheckout(PaymentGatewayConfig $config, CheckoutRequestData $data): HostedCheckout
    {
        $locale = in_array($data->locale, self::LOCALES, true) ? $data->locale : 'auto';
        $form = array_filter([
            'mode' => 'payment',
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'client_reference_id' => $data->reference,
            'customer_email' => $data->customerEmail,
            'locale' => $locale,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($data->amount->currency),
            'line_items[0][price_data][unit_amount]' => $data->amount->minor,
            'line_items[0][price_data][product_data][name]' => $data->description,
            'metadata[reference]' => $data->reference,
            'payment_intent_data[metadata][reference]' => $data->reference,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->asForm()
            ->withHeaders(['Idempotency-Key' => 'checkout-'.$data->reference])
            ->post('/v1/checkout/sessions', $form), $config);

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url)) {
            throw PaymentGatewayException::gateway('Stripe returned no checkout session.');
        }

        return new HostedCheckout($id, $url);
    }

    public function verify(PaymentGatewayConfig $config, string $checkoutId): PaymentResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->get('/v1/checkout/sessions/'.rawurlencode($checkoutId)), $config);

        return $this->resultFromSession((array) $response->json());
    }

    public function parseWebhook(PaymentGatewayConfig $config, Request $request): WebhookNotification
    {
        $secret = $config->credential('webhook_secret');
        $payload = $request->getContent();

        if ($secret === null || ! $this->signatureMatches($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            throw PaymentGatewayException::invalidSignature();
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            throw PaymentGatewayException::invalidSignature();
        }

        $session = $event['data']['object'] ?? [];
        $session = is_array($session) ? $session : [];
        $type = $event['type'];

        $outcome = match ($type) {
            'checkout.session.completed' => ($session['payment_status'] ?? null) === 'paid' ? PaymentOutcome::Paid : PaymentOutcome::Pending,
            'checkout.session.async_payment_succeeded' => PaymentOutcome::Paid,
            'checkout.session.async_payment_failed' => PaymentOutcome::Failed,
            'checkout.session.expired' => PaymentOutcome::Cancelled,
            default => null,
        };

        $checkoutId = $outcome !== null && is_string($session['id'] ?? null) ? $session['id'] : null;

        return new WebhookNotification(
            eventId: $event['id'],
            type: $type,
            checkoutId: $checkoutId,
            outcome: $outcome ?? PaymentOutcome::Pending,
        );
    }

    /**
     * Stripe-Signature: `t=<unix>,v1=<hex>[,v1=<hex>…]`; any v1 may match.
     */
    public function signatureMatches(string $payload, string $header, string $secret, ?int $now = null): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || abs(($now ?? time()) - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function resultFromSession(array $session): PaymentResult
    {
        $id = (string) ($session['id'] ?? '');
        $paymentIntent = $session['payment_intent'] ?? null;
        $paymentIntent = is_array($paymentIntent) ? ($paymentIntent['id'] ?? null) : $paymentIntent;

        $outcome = match (true) {
            ($session['payment_status'] ?? null) === 'paid', ($session['payment_status'] ?? null) === 'no_payment_required' => PaymentOutcome::Paid,
            ($session['status'] ?? null) === 'expired' => PaymentOutcome::Cancelled,
            default => PaymentOutcome::Pending,
        };

        return new PaymentResult(
            outcome: $outcome,
            checkoutId: $id,
            transactionId: is_string($paymentIntent) ? $paymentIntent : null,
            details: array_filter([
                'status' => $session['status'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    protected function send(callable $call, PaymentGatewayConfig $config): Response
    {
        $secret = $config->credential('secret_key');

        if ($secret === null) {
            throw PaymentGatewayException::misconfigured();
        }

        try {
            $response = $call(Http::baseUrl(self::BASE_URL)->withToken($secret)->acceptJson()->timeout(self::TIMEOUT_SECONDS));
        } catch (ConnectionException $exception) {
            throw PaymentGatewayException::unreachable($exception->getMessage());
        }

        if ($response->failed()) {
            $message = $response->json('error.message');

            throw PaymentGatewayException::gateway(is_string($message) ? $message : 'Stripe HTTP '.$response->status());
        }

        return $response;
    }
}
