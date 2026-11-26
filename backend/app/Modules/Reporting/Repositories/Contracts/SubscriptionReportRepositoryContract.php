<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Support\Collection;

/**
 * Read-only subscription / invoice data (central Billing tables) for the reports.
 *
 * @extends RepositoryContract<Subscription>
 */
interface SubscriptionReportRepositoryContract extends RepositoryContract
{
    /**
     * Every subscription that existed before the window ended (MRR / churn / trials are
     * computed from these in Support\SubscriptionMetrics).
     *
     * @return Collection<int, object{id: int, tenant_id: int, plan_id: int|null, plan_name: string|null, term: string, status: string, amount_minor: int, trial_ends_at: string|null, created_at: string|null, cancelled_at: string|null, expired_at: string|null}>
     */
    public function lifecycles(ReportFilterData $filters): Collection;

    /**
     * Paid subscription / add-on invoices in the window.
     *
     * @return array{invoices: int, amount: int}
     */
    public function paidInvoices(ReportFilterData $filters): array;
}
