<?php

namespace App\Modules\Billing\Services\Ledger;

use App\Modules\Billing\DTOs\Ledger\LedgerBackfillReport;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Throwable;

/**
 * Posts the money history that predates the ledger (decision #74 d): every paid order of
 * every store, then its paid settlements, then paid platform invoices — through the same
 * FinancePostings methods and the SAME idempotency keys as the live listeners
 * (`order:<tenant>:<uuid>:paid`, `settlement:<id>`, `invoice:<id>`). A second run, or a
 * live event after the back-fill, therefore posts nothing twice; order postings are
 * deltas against what the ledger already holds for the order, so they always land on the
 * ORDER_FORMULAS §3 figures. A dry run only reports what would be posted.
 */
class LedgerBackfillService extends BaseService
{
    /** Stores whose database exists (pending / provisioning / failed have none). */
    private const STATUSES = [TenantStatus::Active, TenantStatus::Trial, TenantStatus::Suspended, TenantStatus::Cancelled];

    public function __construct(
        private readonly FinancePostings $postings,
        private readonly LedgerRepositoryContract $ledger,
        private readonly TenantRepositoryContract $tenants,
        private readonly OrderRepositoryContract $orders,
        private readonly OrderSettlementRepositoryContract $settlements,
        private readonly InvoiceRepositoryContract $invoices,
        private readonly TenantRunner $runner,
    ) {}

    /**
     * @param  list<int>  $tenantIds  only these stores (empty = all)
     */
    public function run(array $tenantIds = [], bool $dryRun = false): LedgerBackfillReport
    {
        $report = new LedgerBackfillReport($dryRun);

        $this->tenants->query()
            ->whereIn('status', array_map(static fn (TenantStatus $status): string => $status->value, self::STATUSES))
            ->when($tenantIds !== [], static fn ($query) => $query->whereKey($tenantIds))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($report, $dryRun): void {
                $id = (int) $tenant->id;

                try {
                    $this->runner->run($id, fn () => $this->orders($id, $report, $dryRun));
                    $this->settlementsOf($id, $report, $dryRun);
                    $this->invoicesOf($id, $report, $dryRun);
                    $report->stores++;
                } catch (Throwable $exception) {
                    report($exception);
                    $report->failures[$id] = $exception->getMessage();
                }
            });

        return $report;
    }

    private function orders(int $tenantId, LedgerBackfillReport $report, bool $dryRun): void
    {
        $this->orders->paidCursor()->each(function (Order $order) use ($tenantId, $report, $dryRun): void {
            $key = FinancePostings::orderKey($tenantId, $order->uuid);

            if ($this->ledger->findByKey($key) !== null) {
                $report->ordersExisting++;

                return;
            }

            if ($dryRun) {
                $deltas = $this->postings->orderDeltas($tenantId, $order);
                $deltas['payable'] === 0 && $deltas['receivable'] === 0 ? $report->ordersUnchanged++ : $report->ordersPosted++;

                return;
            }

            $this->postings->order($tenantId, $order, $key, occurredAt: $order->paid_at) !== null
                ? $report->ordersPosted++
                : $report->ordersUnchanged++;
        });
    }

    private function settlementsOf(int $tenantId, LedgerBackfillReport $report, bool $dryRun): void
    {
        $this->settlements->paidCursor([$tenantId])->each(function (OrderSettlement $settlement) use ($report, $dryRun): void {
            if ($settlement->amount <= 0) {
                return;
            }

            if ($this->ledger->findByKey('settlement:'.$settlement->id) !== null) {
                $report->settlementsExisting++;

                return;
            }

            if (! $dryRun) {
                $this->postings->settlement($settlement);
            }

            $report->settlementsPosted++;
        });
    }

    private function invoicesOf(int $tenantId, LedgerBackfillReport $report, bool $dryRun): void
    {
        $this->invoices->paidCursor([$tenantId])->each(function (Invoice $invoice) use ($report, $dryRun): void {
            if ($invoice->total_minor <= 0) {
                return;
            }

            if ($this->ledger->findByKey('invoice:'.$invoice->id) !== null) {
                $report->invoicesExisting++;

                return;
            }

            if (! $dryRun) {
                $this->postings->invoicePaid($invoice);
            }

            $report->invoicesPosted++;
        });
    }
}
