<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Finance › Invoices (VEN-061, Figma Vendor Panel 88:110393): the store's
 * platform invoices (subscription, renewals, language / AI add-ons, manual) — number +
 * first line, issue date, amount, status, view (detail panel VEN-061a), print / PDF and
 * pay (unpaid, finance.subscription.manage). Filters: search (number), status, type,
 * issue date range. Pinned to the current store (central DB, tenant scope + tenant id).
 *
 * @extends BaseDataTable<Invoice>
 */
final class VendorBillingInvoicesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'type', 'from', 'to'];

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
        return $this->invoices->listing(['tenant_id' => $this->tenantId])->with('items');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $from = self::date($filters['from'] ?? null);
        $to = self::date($filters['to'] ?? null);

        return $this->invoices->listing([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'type' => $filters['type'] ?? null,
            'tenant_id' => $this->tenantId,
        ])
            ->with('items')
            ->when($from !== null, fn (Builder $q) => $q->where('invoices.issued_at', '>=', $from?->startOfDay()))
            ->when($to !== null, fn (Builder $q) => $q->where('invoices.issued_at', '<=', $to?->endOfDay()));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->setRowAttr(['data-invoice-row' => static fn (Invoice $invoice): int => $invoice->id])
            ->editColumn('number', fn (Invoice $invoice): string => view('billing::vendor.invoices._number-cell', ['invoice' => $invoice])->render())
            ->editColumn('issued_at', fn (Invoice $invoice): string => '<span class="whitespace-nowrap">'.e((string) $invoice->issued_at?->locale(app()->getLocale())->translatedFormat('j M Y')).'</span>')
            ->editColumn('total_minor', fn (Invoice $invoice): string => '<bdi dir="ltr" class="font-semibold text-ink">'.e($invoice->total()->format()).'</bdi>')
            ->editColumn('status', fn (Invoice $invoice): string => view('billing::admin._status', ['status' => $invoice->status])->render())
            ->addColumn('actions', fn (Invoice $invoice): string => view('billing::vendor.invoices._actions', ['invoice' => $invoice, 'canPay' => $this->canPay])->render())
            ->rawColumns(['number', 'issued_at', 'total_minor', 'status', 'actions']);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null;
    }
}
