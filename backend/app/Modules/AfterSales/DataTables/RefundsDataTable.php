<?php

namespace App\Modules\AfterSales\DataTables;

use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Support\AfterSalesAccess;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Refunds list (ADM-024 all stores, Figma 70:38484 / VEN-023 current store): reference +
 * age, order + return, store, source, gateway, amount, status, retry / complete / reject.
 *
 * @extends BaseDataTable<Refund>
 */
final class RefundsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'source', 'tenant_id', 'from', 'to'];

    private string $surface = 'admin';

    private bool $canManage = false;

    public function __construct(private readonly RefundRepositoryContract $refunds) {}

    public function forSurface(string $surface, bool $canManage): self
    {
        $this->surface = $surface;
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->refunds->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->refunds->listing($filters)->with('tenant');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('refund', static fn (Builder $query, string $order) => $query->orderBy('requested_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('order_number', $direction($order)))
            ->orderColumn('source', static fn (Builder $query, string $order) => $query->orderBy('source', $direction($order)))
            ->orderColumn('gateway', static fn (Builder $query, string $order) => $query->orderBy('gateway', $direction($order)))
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('refund', fn (Refund $refund): string => '<span class="block font-medium text-ink" dir="ltr">'.e($refund->reference).'</span><span class="text-xs text-ink-secondary">'.e((string) $refund->requested_at->diffForHumans()).'</span>')
            ->addColumn('order', fn (Refund $refund): string => '<span class="block font-medium text-ink" dir="ltr">#'.e($refund->order_number).'</span><span class="text-xs text-ink-secondary">'.e($refund->returnRequest !== null ? $refund->returnRequest->reference : $refund->source->label()).'</span>')
            ->addColumn('store', fn (Refund $refund): string => e((string) $refund->tenant?->getAttribute('name')))
            ->addColumn('source', fn (Refund $refund): string => e($refund->source->label()))
            ->addColumn('gateway', fn (Refund $refund): string => e($refund->gateway !== null ? (PaymentGatewayCode::tryFrom($refund->gateway)?->brand() ?? $refund->gateway) : $refund->method->label()))
            ->addColumn('amount', fn (Refund $refund): string => '<span class="font-semibold text-ink" dir="ltr">'.e($refund->money()->format()).'</span>')
            ->addColumn('status', fn (Refund $refund): string => view('commerce::partials.status-badge', ['value' => $refund->status, 'context' => 'refund'])->render())
            ->addColumn('actions', fn (Refund $refund): string => view('after-sales::partials._refund-actions', [
                'refund' => $refund,
                'actions' => $this->canAct($refund) ? AfterSalesAccess::refundActions($refund) : [],
                'surface' => $this->surface,
            ])->render())
            ->rawColumns(['refund', 'order', 'amount', 'status', 'actions']);
    }

    private function canAct(Refund $refund): bool
    {
        if (! $this->canManage) {
            return false;
        }

        return $this->surface === 'vendor' ? AfterSalesAccess::vendorCanActOnRefund($refund) : AfterSalesAccess::adminCanActOnRefund($refund);
    }
}
