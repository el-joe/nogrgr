<?php

namespace App\Modules\Integrations\Repositories\Eloquent;

use App\Modules\Integrations\Models\GatewayCheckoutRoute;
use App\Modules\Integrations\Repositories\Contracts\GatewayCheckoutRouteRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<GatewayCheckoutRoute>
 */
class GatewayCheckoutRouteRepository extends BaseRepository implements GatewayCheckoutRouteRepositoryContract
{
    protected function model(): string
    {
        return GatewayCheckoutRoute::class;
    }

    public function record(string $gateway, string $checkoutId, int $tenantId, string $paymentReference): GatewayCheckoutRoute
    {
        /** @var GatewayCheckoutRoute $route */
        $route = GatewayCheckoutRoute::withoutTenantScope()->firstOrCreate(
            ['gateway' => $gateway, 'checkout_id' => $checkoutId],
            ['tenant_id' => $tenantId, 'payment_reference' => $paymentReference],
        );

        return $route;
    }

    public function findRoute(string $gateway, string $checkoutId): ?GatewayCheckoutRoute
    {
        /** @var GatewayCheckoutRoute|null $route */
        $route = GatewayCheckoutRoute::withoutTenantScope()->where('gateway', $gateway)->where('checkout_id', $checkoutId)->first();

        return $route;
    }
}
