<?php

namespace App\Modules\Commerce\Contracts;

use App\Shared\Money\Money;

/**
 * Refund totals of a store order, implemented by the AfterSales module (refunds live
 * there). Commerce reads it for the settlement figures (a partial refund lowers the
 * vendor share) and the refundable cap.
 */
interface OrderRefundLedger
{
    /**
     * Completed refunds.
     */
    public function refunded(int $tenantId, string $orderUuid): Money;

    /**
     * Completed + open (pending / processing / failed) refunds: what the cap must keep free.
     */
    public function reserved(int $tenantId, string $orderUuid): Money;

    /**
     * Completed refunds of several orders of a store (list KPIs).
     *
     * @param  list<string>  $orderUuids
     */
    public function refundedForOrders(int $tenantId, array $orderUuids): Money;
}
