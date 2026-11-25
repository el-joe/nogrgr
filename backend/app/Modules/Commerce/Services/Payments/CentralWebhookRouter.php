<?php

namespace App\Modules\Commerce\Services\Payments;

use App\Modules\Commerce\Contracts\GatewayRefundUpdates;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Repositories\Contracts\GatewayCheckoutRouteRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Storefront payments taken with the platform's (central) gateway keys notify the central
 * webhook endpoint (POST {central}/webhooks/payments/{gateway}). When Billing does not
 * know the checkout, this router verifies the event with the central vendor-payments
 * config (the storefront driver, central webhook secret), finds the store through
 * `gateway_checkout_routes` and settles the attempt in the store's database through
 * PaymentService::handleNotification() (replay-safe per gateway event id; only attempts
 * paid with the central keys). Refund events of central-key payments are routed to the
 * refund's store the same way (decision #72).
 */
class CentralWebhookRouter
{
    public function __construct(
        private readonly StorefrontGatewayService $gateways,
        private readonly PaymentGatewayRepositoryContract $centralGateways,
        private readonly GatewayCheckoutRouteRepositoryContract $routes,
        private readonly TenantRunner $tenants,
        private readonly GatewayRefundUpdates $refundUpdates,
    ) {}

    /**
     * @return string|null processed | duplicate | ignored, or null when the event is not a
     *                     routable storefront event (unknown gateway, bad signature, unknown checkout)
     */
    public function route(PaymentGatewayCode $code, Request $request): ?string
    {
        $central = $this->centralGateways->findByCode($code);

        if ($central === null || ! $this->gateways->supports($code)) {
            return null;
        }

        $config = $this->centralGateways->configFor($central, StorefrontGatewayService::PURPOSE);

        try {
            $notification = $this->gateways->driver($code)->parseWebhook($config, $request);
        } catch (PaymentGatewayException) {
            return null;
        }

        if ($notification->isRefundEvent()) {
            // Refunds of central-key payments (decision #72): the refund row names the store.
            $tenantId = $this->refundUpdates->tenantOf($code, $notification->refunds[0], centralOnly: true);
        } elseif ($notification->isRelevant()) {
            $tenantId = $this->routes->findRoute($code->value, (string) $notification->checkoutId)?->tenant_id;
        } else {
            return null;
        }

        if ($tenantId === null) {
            return null;
        }

        try {
            return $this->tenants->run($tenantId, static fn (): string => app(PaymentService::class)->handleNotification($code, $notification, $config, centralOnly: true));
        } catch (ModelNotFoundException) {
            return 'ignored'; // the store was deleted
        }
    }
}
