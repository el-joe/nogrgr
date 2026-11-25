<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Localization\Services\LanguagePurchaseService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Localization › Language purchases (ADM-094): Billing add-on purchases of
 * languages and AI translation, read from Billing (never copied). Filters: type,
 * status, gateway, search (store, item, invoice number).
 *
 * @extends BaseDataTable<AddonPurchase>
 */
final class LanguagePurchasesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'type', 'status', 'gateway'];

    public function __construct(
        private readonly AddonPurchaseRepositoryContract $purchases,
        private readonly LanguagePurchaseService $service,
    ) {}

    protected function query(): Builder
    {
        return $this->purchases->adminListing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->purchases->adminListing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->addColumn('store', fn (AddonPurchase $purchase): string => view('localization::admin.purchases._store-cell', ['purchase' => $purchase])->render())
            ->addColumn('purchase', fn (AddonPurchase $purchase): string => e($this->service->describe($purchase)))
            ->editColumn('amount_minor', fn (AddonPurchase $purchase): string => '<bdi dir="ltr" class="font-medium">'.e($purchase->amount()->format()).'</bdi>')
            ->editColumn('created_at', fn (AddonPurchase $purchase): string => e($purchase->created_at?->translatedFormat('j M Y') ?? '—'))
            ->editColumn('gateway', fn (AddonPurchase $purchase): string => e(PaymentGateway::tryFrom((string) $purchase->getAttribute('gateway'))?->label() ?? '—'))
            ->editColumn('invoice_number', fn (AddonPurchase $purchase): string => '<span dir="ltr">'.e((string) ($purchase->getAttribute('invoice_number') ?? '—')).'</span>')
            ->editColumn('status', fn (AddonPurchase $purchase): string => view('localization::admin.purchases._status', ['status' => $purchase->status])->render())
            ->addColumn('actions', fn (AddonPurchase $purchase): string => view('localization::admin.purchases._actions', ['purchase' => $purchase])->render())
            ->orderColumn('amount_minor', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.amount_minor', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.created_at', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('addon_purchases.status', $order === 'desc' ? 'desc' : 'asc'))
            ->rawColumns(['store', 'amount_minor', 'invoice_number', 'status', 'actions']);
    }
}
