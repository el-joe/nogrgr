<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Read side of the finance pages (invoice detail, payment log detail, tab counts).
 */
class InvoiceQueryService extends BaseService
{
    public function __construct(
        private readonly InvoiceRepositoryContract $invoices,
        private readonly PaymentLogRepositoryContract $logs,
    ) {}

    public function invoice(int $id): Invoice
    {
        return $this->invoices->findWithItems($id);
    }

    public function paymentLog(int $id): PaymentLog
    {
        return $this->logs->findForDetail($id);
    }

    /**
     * @return array<string, int>
     */
    public function invoiceCounts(): array
    {
        return $this->invoices->countsByStatus();
    }
}
