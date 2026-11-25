<?php

namespace App\Modules\AfterSales\Support;

use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\Commerce\Contracts\OrderRefundLedger;
use App\Shared\Money\Money;

/**
 * Commerce's view of the refunds of an order (bound to OrderRefundLedger).
 */
class RefundLedger implements OrderRefundLedger
{
    public function __construct(private readonly RefundRepositoryContract $refunds) {}

    public function refunded(int $tenantId, string $orderUuid): Money
    {
        return Money::ofMinor($this->refunds->sumForOrder($tenantId, $orderUuid, [RefundStatus::Completed->value]));
    }

    public function reserved(int $tenantId, string $orderUuid): Money
    {
        return Money::ofMinor($this->refunds->sumForOrder($tenantId, $orderUuid, RefundStatus::reservingValues()));
    }
}
