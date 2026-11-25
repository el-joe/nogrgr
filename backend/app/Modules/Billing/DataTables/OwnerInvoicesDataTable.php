<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Owner portal › Invoices (OWN-005): invoices of every store the owner owns.
 *
 * @extends BaseDataTable<Invoice>
 */
final class OwnerInvoicesDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'tenant_id', 'search'];

    private int $ownerId = 0;

    public function __construct(private readonly InvoiceRepositoryContract $invoices) {}

    public function forOwner(int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->invoices->listing(['owner_id' => $this->ownerId]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->invoices->listing([...$filters, 'owner_id' => $this->ownerId]);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('number', fn (Invoice $invoice): string => view('billing::owner.invoices._number-cell', ['invoice' => $invoice])->render())
            ->addColumn('store', fn (Invoice $invoice): string => e((string) $invoice->tenant?->getAttribute('name')))
            ->editColumn('type', fn (Invoice $invoice): string => e($invoice->type->label()))
            ->editColumn('total_minor', fn (Invoice $invoice): string => '<bdi dir="ltr" class="font-medium">'.e($invoice->total()->format()).'</bdi>')
            ->editColumn('status', fn (Invoice $invoice): string => view('billing::admin._status', ['status' => $invoice->status])->render())
            ->addColumn('actions', fn (Invoice $invoice): string => view('billing::owner.invoices._actions', ['invoice' => $invoice])->render())
            ->rawColumns(['number', 'total_minor', 'status', 'actions']);
    }
}
