<?php

namespace App\Modules\Commerce\Contracts;

use App\Modules\Integrations\DTOs\RefundNotification;
use App\Modules\Integrations\Enums\PaymentGatewayCode;

/**
 * Refund outcomes reported by a gateway webhook (decision #72), implemented by AfterSales.
 * Called in the store's tenant context after the signature and the replay check.
 */
interface GatewayRefundUpdates
{
    /**
     * @param  bool  $centralOnly  only refunds of payments taken with the platform's keys
     * @return bool true when a refund of this store was found (even if already final)
     */
    public function apply(int $tenantId, PaymentGatewayCode $gateway, RefundNotification $update, bool $centralOnly = false): bool;

    /**
     * The store of a refund the gateway reports (central endpoint routing), or null.
     */
    public function tenantOf(PaymentGatewayCode $gateway, RefundNotification $update, bool $centralOnly = false): ?int;
}
