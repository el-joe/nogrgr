<?php

namespace App\Modules\Integrations\Repositories\Contracts;

use App\Modules\Integrations\Models\GatewayCheckoutRoute;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<GatewayCheckoutRoute>
 */
interface GatewayCheckoutRouteRepositoryContract extends RepositoryContract
{
    /**
     * Remember which store a central-keys checkout belongs to (idempotent).
     */
    public function record(string $gateway, string $checkoutId, int $tenantId, string $paymentReference): GatewayCheckoutRoute;

    public function findRoute(string $gateway, string $checkoutId): ?GatewayCheckoutRoute;
}
