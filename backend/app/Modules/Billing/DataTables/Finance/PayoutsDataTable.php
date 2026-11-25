<?php

namespace App\Modules\Billing\DataTables\Finance;

use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Payouts platform → store (ADM-052, Figma 74:61743 / VEN-064 88:111108): reference +
 * date, store + bank on file, method, amount, transaction reference, status, view / edit
 * / cancel.
 *
 * @extends BaseDataTable<TenantPayout>
 */
final class PayoutsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'method', 'tenant_id'];

    private ?int $tenantId = null;

    private string $surface = 'admin';

    private bool $canManage = false;

    public function __construct(private readonly TenantPayoutRepositoryContract $payouts) {}

    public function forTenant(?int $tenantId, string $surface, bool $canManage = false): self
    {
        $this->tenantId = $tenantId;
        $this->surface = $surface;
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->payouts->listing([], $this->tenantId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->payouts->listing($filters, $this->tenantId);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('payout', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('method', static fn (Builder $query, string $order) => $query->orderBy('method', $direction($order)))
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->orderColumn('reference', static fn (Builder $query, string $order) => $query->orderBy('transaction_reference', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('payout', fn (TenantPayout $payout): string => '<span class="block font-medium text-ink" dir="ltr">'.e((string) $payout->reference).'</span><span class="text-xs text-ink-secondary">'.e($this->when($payout)).'</span>')
            ->addColumn('store', fn (TenantPayout $payout): string => view('billing::admin.finance.payouts._store-cell', ['payout' => $payout])->render())
            ->addColumn('method', fn (TenantPayout $payout): string => e($payout->method->label()))
            ->addColumn('amount', fn (TenantPayout $payout): string => '<span class="font-semibold text-ink" dir="ltr">'.e($payout->money()->format()).'</span>')
            ->addColumn('reference', fn (TenantPayout $payout): string => '<span dir="ltr">'.e($payout->transaction_reference ?? '—').'</span>')
            ->addColumn('status', fn (TenantPayout $payout): string => view('billing::finance._badge', ['tone' => $payout->status->tone(), 'label' => $payout->status->label()])->render())
            ->addColumn('actions', fn (TenantPayout $payout): string => $this->surface === 'admin'
                ? view('billing::admin.finance.payouts._actions-cell', ['payout' => $payout, 'canManage' => $this->canManage])->render()
                : '')
            ->rawColumns(['payout', 'store', 'amount', 'reference', 'status', 'actions']);
    }

    private function when(TenantPayout $payout): string
    {
        $locale = app()->getLocale();

        return match (true) {
            $payout->paid_at !== null => $payout->paid_at->locale($locale)->translatedFormat('j M Y'),
            $payout->scheduled_for !== null && $payout->status->isOpen() => (string) __('billing::finance.payouts.scheduled_on', ['date' => $payout->scheduled_for->locale($locale)->translatedFormat('j M Y')]),
            default => (string) $payout->created_at?->locale($locale)->translatedFormat('j M Y'),
        };
    }
}
