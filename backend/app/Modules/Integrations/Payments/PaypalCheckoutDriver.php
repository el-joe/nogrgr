<?php

namespace App\Modules\Integrations\Payments;

use App\Modules\Integrations\Contracts\PaymentGatewayDriver;
use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\HostedCheckout;
use App\Modules\Integrations\DTOs\PaymentResult;
use App\Modules\Integrations\DTOs\WebhookNotification;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Checkout over Orders v2 (sandbox / live host by mode):
 *   createCheckout  POST /v2/checkout/orders (intent CAPTURE, custom_id = our reference,
 *                   PayPal-Request-Id for idempotency) → the payer-action link
 *   verify          POST /v2/checkout/orders/{id}/capture (idempotent request id);
 *                   ORDER_NOT_APPROVED → pending, already captured → read the order
 *   parseWebhook    POST /v1/notifications/verify-webhook-signature with the
 *                   transmission headers and the configured webhook id
 */
class PaypalCheckoutDriver implements PaymentGatewayDriver
{
    public const BASE_URLS = ['test' => 'https://api-m.sandbox.paypal.com', 'live' => 'https://api-m.paypal.com'];

    public const TIMEOUT_SECONDS = 15;

    public function createCheckout(PaymentGatewayConfig $config, CheckoutRequestData $data): HostedCheckout
    {
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $data->reference,
                'custom_id' => $data->reference,
                'description' => mb_substr($data->description, 0, 127),
                'amount' => ['currency_code' => strtoupper($data->amount->currency), 'value' => $data->amount->toDecimal()],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => array_filter([
                'brand_name' => mb_substr((string) config('app.name'), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'locale' => $data->locale === 'ar' ? 'ar-SA' : null,
                'return_url' => $data->successUrl,
                'cancel_url' => $data->cancelUrl,
            ])]],
        ];

        $response = $this->send($config, fn (PendingRequest $http): Response => $http
            ->withHeaders(['PayPal-Request-Id' => 'order-'.$data->reference, 'Prefer' => 'return=representation'])
            ->post('/v2/checkout/orders', $body));

        $id = $response->json('id');
        $url = collect((array) $response->json('links'))
            ->first(static fn (mixed $link): bool => is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true));

        if (! is_string($id) || ! is_array($url) || ! is_string($url['href'] ?? null)) {
            throw PaymentGatewayException::gateway('PayPal returned no approval link.');
        }

        return new HostedCheckout($id, $url['href']);
    }

    public function verify(PaymentGatewayConfig $config, string $checkoutId): PaymentResult
    {
        $path = '/v2/checkout/orders/'.rawurlencode($checkoutId);
        $response = $this->send($config, fn (PendingRequest $http): Response => $http
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$checkoutId, 'Prefer' => 'return=representation'])
            ->withBody('{}', 'application/json')
            ->post($path.'/capture'), allowUnprocessable: true);

        if ($response->status() === 422) {
            $issue = $response->json('details.0.issue');

            if ($issue === 'ORDER_NOT_APPROVED') {
                return new PaymentResult(PaymentOutcome::Pending, $checkoutId);
            }

            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                $response = $this->send($config, fn (PendingRequest $http): Response => $http->get($path));
            } else {
                return new PaymentResult(PaymentOutcome::Failed, $checkoutId, reason: is_string($issue) ? $issue : 'UNPROCESSABLE_ENTITY');
            }
        }

        return $this->resultFromOrder((array) $response->json(), $checkoutId);
    }

    public function parseWebhook(PaymentGatewayConfig $config, Request $request): WebhookNotification
    {
        $webhookId = $config->credential('webhook_id');
        $event = json_decode($request->getContent(), true);

        if ($webhookId === null || ! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['event_type'] ?? null)) {
            throw PaymentGatewayException::invalidSignature();
        }

        $headers = [];

        foreach (['auth_algo' => 'PAYPAL-AUTH-ALGO', 'cert_url' => 'PAYPAL-CERT-URL', 'transmission_id' => 'PAYPAL-TRANSMISSION-ID', 'transmission_sig' => 'PAYPAL-TRANSMISSION-SIG', 'transmission_time' => 'PAYPAL-TRANSMISSION-TIME'] as $key => $header) {
            $value = $request->header($header);

            if (! is_string($value) || $value === '') {
                throw PaymentGatewayException::invalidSignature();
            }

            $headers[$key] = $value;
        }

        $verification = $this->send($config, fn (PendingRequest $http): Response => $http->post('/v1/notifications/verify-webhook-signature', [
            ...$headers,
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]));

        if ($verification->json('verification_status') !== 'SUCCESS') {
            throw PaymentGatewayException::invalidSignature();
        }

        $type = $event['event_type'];
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        [$checkoutId, $outcome, $needsConfirmation] = match ($type) {
            'CHECKOUT.ORDER.APPROVED' => [$resource['id'] ?? null, PaymentOutcome::Pending, true],
            'PAYMENT.CAPTURE.COMPLETED' => [$resource['supplementary_data']['related_ids']['order_id'] ?? null, PaymentOutcome::Paid, false],
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => [$resource['supplementary_data']['related_ids']['order_id'] ?? null, PaymentOutcome::Failed, false],
            'CHECKOUT.ORDER.VOIDED' => [$resource['id'] ?? null, PaymentOutcome::Cancelled, false],
            default => [null, PaymentOutcome::Pending, false],
        };

        return new WebhookNotification(
            eventId: $event['id'],
            type: $type,
            checkoutId: is_string($checkoutId) ? $checkoutId : null,
            outcome: $outcome,
            needsConfirmation: $needsConfirmation,
            reason: is_string($resource['status_details']['reason'] ?? null) ? $resource['status_details']['reason'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function resultFromOrder(array $order, string $checkoutId): PaymentResult
    {
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
        $capture = is_array($capture) ? $capture : [];
        $captureStatus = $capture['status'] ?? null;

        $outcome = match (true) {
            ($order['status'] ?? null) === 'COMPLETED' && in_array($captureStatus, ['COMPLETED', null], true) => PaymentOutcome::Paid,
            $captureStatus === 'PENDING' => PaymentOutcome::Pending,
            in_array($captureStatus, ['DECLINED', 'FAILED'], true) => PaymentOutcome::Failed,
            ($order['status'] ?? null) === 'VOIDED' => PaymentOutcome::Cancelled,
            default => PaymentOutcome::Pending,
        };

        return new PaymentResult(
            outcome: $outcome,
            checkoutId: $checkoutId,
            transactionId: is_string($capture['id'] ?? null) ? $capture['id'] : null,
            reason: is_string($capture['status_details']['reason'] ?? null) ? $capture['status_details']['reason'] : null,
            details: array_filter([
                'status' => $order['status'] ?? null,
                'capture_status' => $captureStatus,
                'amount' => $capture['amount']['value'] ?? null,
                'currency' => $capture['amount']['currency_code'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Authenticated call (client-credentials token per call; tokens are short-lived and
     * a registration makes at most a handful of calls).
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(PaymentGatewayConfig $config, callable $call, bool $allowUnprocessable = false): Response
    {
        $clientId = $config->credential('client_id');
        $secret = $config->credential('client_secret');

        if ($clientId === null || $secret === null) {
            throw PaymentGatewayException::misconfigured();
        }

        $base = self::BASE_URLS[$config->mode === GatewayMode::Live ? 'live' : 'test'];

        try {
            $token = Http::baseUrl($base)->asForm()->acceptJson()->timeout(self::TIMEOUT_SECONDS)
                ->withBasicAuth($clientId, $secret)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($token->failed() || ! is_string($token->json('access_token'))) {
                throw PaymentGatewayException::gateway('PayPal authentication failed (HTTP '.$token->status().').');
            }

            $response = $call(Http::baseUrl($base)->acceptJson()->asJson()->timeout(self::TIMEOUT_SECONDS)->withToken($token->json('access_token')));
        } catch (ConnectionException $exception) {
            throw PaymentGatewayException::unreachable($exception->getMessage());
        }

        if ($response->failed() && ! ($allowUnprocessable && $response->status() === 422)) {
            $message = $response->json('message') ?? $response->json('error_description');

            throw PaymentGatewayException::gateway(is_string($message) ? $message : 'PayPal HTTP '.$response->status());
        }

        return $response;
    }
}
