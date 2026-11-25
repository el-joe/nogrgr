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
 * Stripe through PaymentIntents for the storefront inline card panel (spec 08 §3
 * backend fix): the intent is created server side, the panel confirms it with the
 * client secret (3-D Secure handled by `handleCardAction` in the same panel), the
 * return / webhook check reads the intent. Same contract as the hosted drivers: the
 * "checkout id" is the PaymentIntent id and there is no redirect URL.
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
            'automatic_payment_methods[enabled]' => 'true',
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

        return new WebhookNotification(
            eventId: $event['id'],
            type: $event['type'],
            checkoutId: $outcome !== null && is_string($intent['id'] ?? null) ? $intent['id'] : null,
            outcome: $outcome ?? PaymentOutcome::Pending,
            reason: is_string($error) ? $error : null,
            refunds: $this->refundsFromEvent($event['type'], $intent),
        );
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function resultFromIntent(array $intent): PaymentResult
    {
        $status = $intent['status'] ?? null;
        $error = $intent['last_payment_error']['message'] ?? null;

        $outcome = match (true) {
            $status === 'succeeded' => PaymentOutcome::Paid,
            $status === 'canceled' => PaymentOutcome::Cancelled,
            $status === 'requires_payment_method' && is_string($error) => PaymentOutcome::Failed,
            default => PaymentOutcome::Pending,
        };

        return new PaymentResult(
            outcome: $outcome,
            checkoutId: (string) ($intent['id'] ?? ''),
            transactionId: is_string($intent['latest_charge'] ?? null) ? $intent['latest_charge'] : (is_string($intent['id'] ?? null) ? $intent['id'] : null),
            reason: is_string($error) ? $error : null,
            details: array_filter([
                'status' => $status,
                'amount' => $intent['amount_received'] ?? $intent['amount'] ?? null,
                'currency' => $intent['currency'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
