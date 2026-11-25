<?php

namespace App\Modules\AfterSales\Support;

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Services\RefundService;
use App\Modules\Commerce\Contracts\GatewayRefundUpdates;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Integrations\DTOs\RefundNotification;
use App\Modules\Integrations\Enums\PaymentGatewayCode;

/**
 * Commerce's GatewayRefundUpdates (decision #72): finds the refund a gateway webhook
 * reports (gateway refund id, else our reference) and lets RefundService apply the
 * outcome. Store endpoint: refunds of the current store only; central endpoint
 * (`$centralOnly`): only refunds of payments taken with the platform's keys.
 */
class GatewayRefundWebhooks implements GatewayRefundUpdates
{
    public function __construct(
        private readonly RefundRepositoryContract $refunds,
        private readonly RefundService $service,
    ) {}

    public function apply(int $tenantId, PaymentGatewayCode $gateway, RefundNotification $update, bool $centralOnly = false): bool
    {
        $refund = $this->find($gateway, $update, $centralOnly);

        if ($refund === null || $refund->tenant_id !== $tenantId) {
            return false;
        }

        $this->service->applyGatewayUpdate($refund, $update);

        return true;
    }

    public function tenantOf(PaymentGatewayCode $gateway, RefundNotification $update, bool $centralOnly = false): ?int
    {
        return $this->find($gateway, $update, $centralOnly)?->tenant_id;
    }

    private function find(PaymentGatewayCode $gateway, RefundNotification $update, bool $centralOnly): ?Refund
    {
        $refund = $this->refunds->findByGatewayRefund($gateway->value, $update->refundId, $update->reference);

        if ($refund === null || $refund->method !== RefundMethod::OriginalPayment) {
            return null;
        }

        return $centralOnly && $refund->payment_source !== PaymentSource::Central ? null : $refund;
    }
}
