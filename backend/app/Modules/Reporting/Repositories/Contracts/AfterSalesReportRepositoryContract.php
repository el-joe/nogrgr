<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Read-only return / refund aggregates (central AfterSales tables) for the reports.
 *
 * @extends RepositoryContract<ReturnRequest>
 */
interface AfterSalesReportRepositoryContract extends RepositoryContract
{
    /**
     * Return requests opened in the window (country through the order fact).
     *
     * @return array{returns: int, seller_fault: int}
     */
    public function returnTotals(ReportFilterData $filters): array;

    /**
     * @return array<string, int> reason code => return requests
     */
    public function returnsByReason(ReportFilterData $filters): array;

    /**
     * @return array<string, string> reason code => translated name
     */
    public function reasonNames(): array;

    /**
     * Completed refunds processed in the window.
     *
     * @return array{refunds: int, amount: int}
     */
    public function refundTotals(ReportFilterData $filters): array;

    /**
     * Completed refund amounts per day, keyed `Y-m-d`.
     *
     * @return array<string, int>
     */
    public function refundsDaily(ReportFilterData $filters): array;

    /**
     * Returns to inspect (item received, not yet inspected) across stores.
     */
    public function awaitingInspection(): int;
}
