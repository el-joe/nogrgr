<?php

namespace App\Modules\Commerce\Services\Payments;

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
 * paid with the central keys).
 */
class CentralWebhookRouter
{
    public function __construct(
        private readonly StorefrontGatewayService $gateways,
        private readonly PaymentGatewayRepositoryContract $centralGateways,
        private readonly GatewayCheckoutRouteRepositoryContract $routes,
        private readonly TenantRunner $tenants,
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

        if (! $notification->isRelevant()) {
            return null;
        }

        $route = $this->routes->findRoute($code->value, (string) $notification->checkoutId);

        if ($route === null) {
            return null;
        }

        try {
            return $this->tenants->run($route->tenant_id, static fn (): string => app(PaymentService::class)->handleNotification($code, $notification, $config, centralOnly: true));
        } catch (ModelNotFoundException) {
            return 'ignored'; // the store was deleted
        }
    }
}
