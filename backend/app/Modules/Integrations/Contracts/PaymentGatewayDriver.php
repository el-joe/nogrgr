<?php

namespace App\Modules\Integrations\Contracts;

use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\HostedCheckout;
use App\Modules\Integrations\DTOs\PaymentResult;
use App\Modules\Integrations\DTOs\RefundRequestData;
use App\Modules\Integrations\DTOs\RefundResult;
use App\Modules\Integrations\DTOs\WebhookNotification;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use Illuminate\Http\Request;

/**
 * A hosted-checkout gateway driver (Stripe Checkout, PayPal Orders v2, …). The
 * config carries the purpose's credentials and test/live mode. Drivers never store
 * anything: Billing records the payment log and settles invoices.
 */
interface PaymentGatewayDriver
{
    /**
     * Create the hosted checkout for an amount; the payer is redirected to it.
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(PaymentGatewayConfig $config, CheckoutRequestData $data): HostedCheckout;

    /**
     * Ask the gateway how a checkout ended (and capture it when the gateway needs an
     * explicit capture). Safe to call more than once.
     *
     * @throws PaymentGatewayException
     */
    public function verify(PaymentGatewayConfig $config, string $checkoutId): PaymentResult;

    /**
     * Verify a webhook's signature and read it.
     *
     * @throws PaymentGatewayException invalid signature or misconfigured webhook secret
     */
    public function parseWebhook(PaymentGatewayConfig $config, Request $request): WebhookNotification;

    /**
     * Refund (part of) a captured payment. A gateway decline is a failed result; transport
     * or configuration problems throw.
     *
     * @throws PaymentGatewayException
     */
    public function refund(PaymentGatewayConfig $config, RefundRequestData $data): RefundResult;
}
