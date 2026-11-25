<?php

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Events\RefundFailed;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Gateway refund webhooks (decision #72): Stripe refund.* / charge.refunded and PayPal
| PAYMENT.CAPTURE.REFUNDED complete or fail a pending gateway refund. Signature-checked,
| replay-safe, routed from the store endpoint and (central-key payments) the central one.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    shopGateway($this->shop->tenant, PaymentGatewayCode::Paypal);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'wh-order-1', 'central');
    $this->refund = fn (string $gateway = 'stripe', string $source = 'central'): Refund => Refund::withoutTenantScope()->create([
        'reference' => 'RF-W'.random_int(10000, 99999),
        'tenant_id' => $this->shop->tenant->id,
        'order_uuid' => $this->order->uuid,
        'order_number' => $this->order->number,
        'source' => RefundSource::Manual,
        'reason' => 'Webhook test',
        'currency' => 'USD',
        'amount' => 1000,
        'items_amount' => 1000,
        'method' => RefundMethod::OriginalPayment,
        'gateway' => $gateway,
        'payment_source' => $source,
        'gateway_refund_id' => $gateway === 'stripe' ? 're_test_9' : null,
        'status' => RefundStatus::Processing,
        'attempts' => 1,
        'requested_by_type' => ActorType::Admin,
        'requested_at' => now(),
    ]);
    $this->stripeEvent = fn (string $id, string $status, string $reference): string => (string) json_encode([
        'id' => $id,
        'type' => 'refund.updated',
        'data' => ['object' => ['id' => 're_test_9', 'object' => 'refund', 'status' => $status, 'metadata' => ['reference' => $reference.'-1'], 'failure_reason' => $status === 'failed' ? 'lost_or_stolen_card' : null]],
    ]);
    $this->postStripe = fn (string $url, string $payload, string $secret = 'whsec_central') => $this->call('POST', $url, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TENANT' => (string) $this->shop->tenant->id,
        'HTTP_STRIPE_SIGNATURE' => shopStripeSignature($payload, $secret),
    ], $payload);
});

it('completes a pending Stripe refund from the store webhook once (replay-safe)', function (): void {
    $refund = ($this->refund)();
    Event::fake([RefundCompleted::class]);
    $payload = ($this->stripeEvent)('evt_r1', 'succeeded', $refund->reference);

    ($this->postStripe)(storeApi('payments/webhooks/stripe'), $payload)->assertOk()->assertJsonPath('message', 'processed');
    ($this->postStripe)(storeApi('payments/webhooks/stripe'), $payload)->assertOk()->assertJsonPath('message', 'duplicate');

    expect($refund->fresh()?->status)->toBe(RefundStatus::Completed)
        ->and($refund->attemptLog()->count())->toBe(1);
    Event::assertDispatchedTimes(RefundCompleted::class, 1);

    // A later event about an already completed refund changes nothing.
    ($this->postStripe)(storeApi('payments/webhooks/stripe'), ($this->stripeEvent)('evt_r2', 'failed', $refund->reference))->assertOk();
    expect($refund->fresh()?->status)->toBe(RefundStatus::Completed);
});

it('fails a refund the gateway rejected and refuses a bad signature', function (): void {
    $refund = ($this->refund)();
    Event::fake([RefundFailed::class]);

    ($this->postStripe)(storeApi('payments/webhooks/stripe'), ($this->stripeEvent)('evt_r3', 'succeeded', $refund->reference), 'whsec_wrong')->assertStatus(400);
    expect($refund->fresh()?->status)->toBe(RefundStatus::Processing);

    ($this->postStripe)(storeApi('payments/webhooks/stripe'), ($this->stripeEvent)('evt_r4', 'failed', $refund->reference))->assertOk();
    expect($refund->fresh()?->status)->toBe(RefundStatus::Failed)
        ->and($refund->fresh()?->failure_reason)->toBe('lost_or_stolen_card');
    Event::assertDispatched(RefundFailed::class);
});

it('ignores refunds of other stores and unknown refunds', function (): void {
    $refund = ($this->refund)();
    $other = shopSetup('other-wh');
    Refund::withoutTenantScope()->whereKey($refund->id)->update(['tenant_id' => $other->tenant->id]);

    ($this->postStripe)(storeApi('payments/webhooks/stripe'), ($this->stripeEvent)('evt_r5', 'succeeded', $refund->reference))->assertOk()->assertJsonPath('message', 'ignored');
    expect($refund->fresh()?->status)->toBe(RefundStatus::Processing);
});

it('routes refund events of central-key payments from the central endpoint', function (): void {
    $refund = ($this->refund)();
    $payload = ($this->stripeEvent)('evt_r6', 'succeeded', $refund->reference);

    ($this->postStripe)($this->centralUrl('/webhooks/payments/stripe'), $payload)->assertOk()->assertJsonPath('result', 'processed');
    ($this->postStripe)($this->centralUrl('/webhooks/payments/stripe'), $payload)->assertOk()->assertJsonPath('result', 'duplicate');
    expect($refund->fresh()?->status)->toBe(RefundStatus::Completed);

    // A refund of a payment taken with the store's own keys is not routed centrally.
    $own = ($this->refund)('stripe', 'tenant');
    Refund::withoutTenantScope()->whereKey($own->id)->update(['gateway_refund_id' => 're_own']);
    $ownPayload = (string) json_encode(['id' => 'evt_r7', 'type' => 'refund.updated', 'data' => ['object' => ['id' => 're_own', 'status' => 'succeeded', 'metadata' => []]]]);
    ($this->postStripe)($this->centralUrl('/webhooks/payments/stripe'), $ownPayload)->assertStatus(200);
    expect($own->fresh()?->status)->toBe(RefundStatus::Processing);
});

it('completes a PayPal refund from PAYMENT.CAPTURE.REFUNDED', function (): void {
    $refund = ($this->refund)('paypal');
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);
    $payload = (string) json_encode(['id' => 'WH-EVT-1', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['id' => '9PP1', 'status' => 'COMPLETED', 'invoice_id' => $refund->reference.'-1']]);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_TENANT' => (string) $this->shop->tenant->id, 'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA', 'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/cert', 'HTTP_PAYPAL_TRANSMISSION_ID' => 't1', 'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig', 'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String()];

    $this->call('POST', storeApi('payments/webhooks/paypal'), [], [], [], $headers, $payload)->assertOk()->assertJsonPath('message', 'processed');

    expect($refund->fresh()?->status)->toBe(RefundStatus::Completed)
        ->and($refund->fresh()?->gateway_refund_id)->toBe('9PP1');
});
