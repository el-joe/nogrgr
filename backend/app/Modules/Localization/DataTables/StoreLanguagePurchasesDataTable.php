<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Localization\Services\LanguagePurchaseService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › Languages › Purchases history (VEN-073b): this store's language
 * purchases (Billing add-ons, read only) with amount, status, date and invoice.
 *
 * @extends BaseDataTable<AddonPurchase>
 */
final class StoreLanguagePurchasesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status'];

    private int $tenantId = 0;

    private bool $invoiceLinks = false;

    public function __construct(
        private readonly AddonPurchaseRepositoryContract $purchases,
        private readonly LanguagePurchaseService $service,
    ) {}

    public function forTenant(int $tenantId, bool $invoiceLinks): self
    {
        $this->tenantId = $tenantId;
        $this->invoiceLinks = $invoiceLinks;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->filter($this->purchases->adminListing(), []);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->purchases->adminListing(['status' => $filters['status'] ?? null, 'type' => AddonType::Language->value, 'search' => $filters['search'] ?? null])
            ->where('addon_purchases.tenant_id', $this->tenantId);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->addColumn('purchase', fn (AddonPurchase $purchase): string => e($this->service->describe($purchase)))
            ->editColumn('amount_minor', fn (AddonPurchase $purchase): string => '<bdi dir="ltr" class="font-medium">'.e($purchase->amount()->format()).'</bdi>')
            ->editColumn('created_at', fn (AddonPurchase $purchase): string => e($purchase->created_at?->translatedFormat('j M Y') ?? '—'))
            ->editColumn('status', fn (AddonPurchase $purchase): string => view('localization::admin.purchases._status', ['status' => $purchase->status])->render())
            ->editColumn('invoice_number', fn (AddonPurchase $purchase): string => view('localization::vendor.languages._invoice-cell', ['purchase' => $purchase, 'links' => $this->invoiceLinks])->render())
            ->orderColumn('amount_minor', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.amount_minor', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.created_at', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.status', $order === 'desc' ? 'desc' : 'asc'))
            ->rawColumns(['amount_minor', 'status', 'invoice_number']);
    }
}
