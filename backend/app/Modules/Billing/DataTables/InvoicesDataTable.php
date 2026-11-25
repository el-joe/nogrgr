<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin invoices list (ADM-054).
 *
 * @extends BaseDataTable<Invoice>
 */
final class InvoicesDataTable extends BaseDataTable
{
    public function __construct(private readonly InvoiceRepositoryContract $invoices) {}

    protected function query(): Builder
    {
        return $this->invoices->listing();
    }

    /**
     * The repository listing applies every filter (status, search…).
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->invoices->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('number', fn (Invoice $invoice): string => view('billing::admin.invoices._number-cell', ['invoice' => $invoice])->render())
            ->addColumn('store', fn (Invoice $invoice): string => view('billing::admin._store-cell', ['tenant' => $invoice->tenant])->render())
            ->editColumn('type', fn (Invoice $invoice): string => e($invoice->type->label()))
            ->editColumn('total_minor', fn (Invoice $invoice): string => '<bdi dir="ltr">'.e($invoice->total()->format()).'</bdi>')
            ->editColumn('issued_at', fn (Invoice $invoice): string => e($invoice->issued_at?->translatedFormat('j M Y') ?? '—'))
            ->editColumn('status', fn (Invoice $invoice): string => view('billing::admin._status', ['status' => $invoice->status])->render())
            ->addColumn('actions', fn (Invoice $invoice): string => view('billing::admin.invoices._actions', ['invoice' => $invoice])->render())
            ->rawColumns(['number', 'store', 'total_minor', 'status', 'actions']);
    }
}
