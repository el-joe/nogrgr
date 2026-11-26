<?php

namespace App\Modules\Integrations\Payments;

use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\HostedCheckout;
use App\Modules\Integrations\DTOs\PaymentResult;
use App\Modules\Integrations\DTOs\WebhookNotification;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Stripe through PaymentIntents for the inline card (Stripe.js Card Element, decision #78 b,
 * like the old project): the intent is created server side (card only, USD minor units),
 * the page confirms it with the client secret (3-D Secure inside Stripe's own dialog), and
 * the server then reads the intent back — no webhook is needed (client 2026-11-26):
 *   createCheckout  POST /v1/payment_intents → HostedCheckout(id = pi_…, no URL, client secret)
 *   verify          GET  /v1/payment_intents/{id}: succeeded → paid, canceled → cancelled,
 *                   requires_payment_method (old API: requires_source) with an error →
 *                   failed, otherwise pending;
 *                   transaction id = the intent id (refunds), amount = amount_received
 * Refunds and signatures are inherited from the Checkout driver.
 */
class StripePaymentIntentDriver extends StripeCheckoutDriver
{
    public function createCheckout(PaymentGatewayConfig $config, CheckoutRequestData $data): HostedCheckout
    {
        $form = array_filter([
            'amount' => $data->amount->minor,
            'currency' => strtolower($data->amount->currency),
            'description' => $data->description,
            'receipt_email' => $data->customerEmail,
            'payment_method_types[0]' => 'card',
            'metadata[reference]' => $data->reference,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->asForm()
            ->withHeaders(['Idempotency-Key' => 'intent-'.$data->reference])
            ->post('/v1/payment_intents', $form), $config);

        $id = $response->json('id');
        $secret = $response->json('client_secret');

        if (! is_string($id) || ! is_string($secret)) {
            throw PaymentGatewayException::gateway('Stripe returned no payment intent.');
        }

        return new HostedCheckout($id, '', $secret);
    }

    public function verify(PaymentGatewayConfig $config, string $checkoutId): PaymentResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->get('/v1/payment_intents/'.rawurlencode($checkoutId)), $config);

        return $this->resultFromIntent((array) $response->json());
    }

    /**
     * Not used by the platform (no webhook, client 2026-11-26) but kept signature-checked
     * so a store that does register one gets the same outcomes as `verify()`.
     */
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

        $intent = $event['data']['object'] ?? [];
        $intent = is_array($intent) ? $intent : [];

        $outcome = match ($event['type']) {
            'payment_intent.succeeded' => PaymentOutcome::Paid,
            'payment_intent.payment_failed' => PaymentOutcome::Failed,
            'payment_intent.canceled' => PaymentOutcome::Cancelled,
            default => null,
        };

        $error = $intent['last_payment_error']['message'] ?? null;
        $checkoutId = $outcome !== null && is_string($intent['id'] ?? null) ? $intent['id'] : null;

        return new WebhookNotification(
            eventId: $event['id'],
            type: $event['type'],
            checkoutId: $checkoutId,
            outcome: $outcome ?? PaymentOutcome::Pending,
            reason: is_string($error) ? $error : null,
            refunds: $this->refundsFromEvent($event['type'], $intent),
            transactionId: $checkoutId,
        );
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function resultFromIntent(array $intent): PaymentResult
    {
        $status = $intent['status'] ?? null;
        $error = $intent['last_payment_error']['message'] ?? null;
        $id = is_string($intent['id'] ?? null) ? $intent['id'] : '';

        $outcome = match (true) {
            $status === 'succeeded' => PaymentOutcome::Paid,
            $status === 'canceled' => PaymentOutcome::Cancelled,
            // `requires_source` = the same state on accounts pinned to an old Stripe API version.
            in_array($status, ['requires_payment_method', 'requires_source'], true) && is_string($error) => PaymentOutcome::Failed,
            default => PaymentOutcome::Pending,
        };

        return new PaymentResult(
            outcome: $outcome,
            checkoutId: $id,
            transactionId: $id !== '' ? $id : null,
            reason: is_string($error) ? $error : null,
            details: array_filter([
                'status' => $status,
                'amount' => $status === 'succeeded' ? ($intent['amount_received'] ?? $intent['amount'] ?? null) : ($intent['amount'] ?? null),
                'currency' => $intent['currency'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
