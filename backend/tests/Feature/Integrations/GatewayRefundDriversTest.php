<?php

use App\Modules\Integrations\DTOs\RefundRequestData;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\RefundOutcome;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Payments\PaypalCheckoutDriver;
use App\Modules\Integrations\Payments\StripePaymentIntentDriver;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/*
| PaymentGatewayDriver::refund() (decision #71): Stripe POST /v1/refunds (payment intent
| or charge, idempotency key = our reference), PayPal capture refund. Faked HTTP.
*/

function refundConfig(array $credentials, GatewayMode $mode = GatewayMode::Test): PaymentGatewayConfig
{
    return new PaymentGatewayConfig(['mode' => $mode, 'is_enabled' => true, 'credentials' => $credentials]);
}

it('refunds a Stripe payment intent or charge with an idempotency key', function (): void {
    Http::fake(['https://api.stripe.com/v1/refunds' => Http::sequence()->push(['id' => 're_1', 'status' => 'succeeded', 'amount' => 1234])->push(['id' => 're_2', 'status' => 'pending'])->push(['id' => 're_3', 'status' => 'failed', 'failure_reason' => 'lost_or_stolen_card'])]);
    $driver = app(StripePaymentIntentDriver::class);
    $config = refundConfig(['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1']);

    $done = $driver->refund($config, new RefundRequestData('pi_123', Money::of('12.34'), 'RF-5001-1'));
    $pending = $driver->refund($config, new RefundRequestData('ch_456', Money::of('1.00'), 'RF-5002-1'));
    $failed = $driver->refund($config, new RefundRequestData('pi_789', Money::of('1.00'), 'RF-5003-1'));

    expect($done->outcome)->toBe(RefundOutcome::Completed)->and($done->refundId)->toBe('re_1')
        ->and($pending->outcome)->toBe(RefundOutcome::Pending)
        ->and($failed->outcome)->toBe(RefundOutcome::Failed)->and($failed->reason)->toBe('lost_or_stolen_card');

    Http::assertSent(fn (HttpRequest $request): bool => ($request['payment_intent'] ?? null) === 'pi_123' && (int) $request['amount'] === 1234 && $request->header('Idempotency-Key') === ['refund-RF-5001-1']);
    Http::assertSent(fn (HttpRequest $request): bool => ($request['charge'] ?? null) === 'ch_456');
});

it('refunds a PayPal capture', function (): void {
    Http::fake([
        'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'https://api-m.sandbox.paypal.com/v2/payments/captures/CAP-1/refund' => Http::response(['id' => 'PPR-1', 'status' => 'COMPLETED']),
        'https://api-m.sandbox.paypal.com/v2/payments/captures/CAP-2/refund' => Http::response(['id' => 'PPR-2', 'status' => 'CANCELLED']),
    ]);
    $driver = app(PaypalCheckoutDriver::class);
    $config = refundConfig(['client_id' => 'id', 'client_secret' => 'secret']);

    expect($driver->refund($config, new RefundRequestData('CAP-1', Money::of('10.50'), 'RF-1'))->outcome)->toBe(RefundOutcome::Completed)
        ->and($driver->refund($config, new RefundRequestData('CAP-2', Money::of('1'), 'RF-2'))->outcome)->toBe(RefundOutcome::Failed);

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), 'CAP-1/refund') && $request['amount'] === ['value' => '10.50', 'currency_code' => 'USD'] && $request->header('PayPal-Request-Id') === ['refund-RF-1']);
});
