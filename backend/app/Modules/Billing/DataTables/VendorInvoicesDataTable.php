<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Subscription & balance › invoices of the current store (VEN-060c): number,
 * type, total, status, print / save as PDF and pay (unpaid, with the manage permission).
 *
 * @extends BaseDataTable<Invoice>
 */
final class VendorInvoicesDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'search'];

    private int $tenantId = 0;

    private bool $canPay = false;

    public function __construct(private readonly InvoiceRepositoryContract $invoices) {}

    public function forTenant(int $tenantId, bool $canPay): self
    {
        $this->tenantId = $tenantId;
        $this->canPay = $canPay;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->invoices->listing(['tenant_id' => $this->tenantId]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->invoices->listing([...$filters, 'tenant_id' => $this->tenantId]);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('number', fn (Invoice $invoice): string => '<span class="font-medium text-ink" dir="ltr">'.e($invoice->number).'</span>')
            ->editColumn('type', fn (Invoice $invoice): string => e($invoice->type->label()))
            ->editColumn('issued_at', fn (Invoice $invoice): string => '<span class="whitespace-nowrap" dir="ltr">'.e((string) $invoice->issued_at?->locale(app()->getLocale())->translatedFormat('j M Y')).'</span>')
            ->editColumn('total_minor', fn (Invoice $invoice): string => '<bdi dir="ltr" class="font-medium">'.e($invoice->total()->format()).'</bdi>')
            ->editColumn('status', fn (Invoice $invoice): string => view('billing::admin._status', ['status' => $invoice->status])->render())
            ->addColumn('actions', fn (Invoice $invoice): string => view('billing::vendor.subscription._invoice-actions', ['invoice' => $invoice, 'canPay' => $this->canPay])->render())
            ->rawColumns(['number', 'issued_at', 'total_minor', 'status', 'actions']);
    }
}
