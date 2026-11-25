<?php

namespace App\Modules\Billing\DataTables\Finance;

use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * Ledger per store (ADM-050, Figma 74:59026): store + domain, vendor balance (the
 * platform owes it), owed to platform, last settlement, status (clear / settlement due /
 * overdue), view / mark settlement paid / create payout.
 *
 * @extends BaseDataTable<Tenant>
 */
final class LedgerTenantsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'balance', 'country_id'];

    private bool $canManage = false;

    private bool $canPayout = false;

    public function __construct(private readonly FinancePageService $pages) {}

    public function withAbilities(bool $canManage, bool $canPayout): self
    {
        $this->canManage = $canManage;
        $this->canPayout = $canPayout;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->pages->ledgerQuery();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->pages->ledgerQuery($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('tenant', static fn (Builder $query, string $order) => $query->orderBy('tenants.name', $direction($order)))
            ->orderColumn('payable', static fn (Builder $query, string $order) => $query->orderBy('ledger_payable', $direction($order)))
            ->orderColumn('receivable', static fn (Builder $query, string $order) => $query->orderBy('ledger_receivable', $direction($order)))
            ->orderColumn('last_settlement', static fn (Builder $query, string $order) => $query->orderBy('last_settlement_at', $direction($order)))
            ->addColumn('tenant', fn (Tenant $tenant): string => view('billing::admin._store-cell', ['tenant' => $tenant])->render())
            ->addColumn('payable', fn (Tenant $tenant): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor((int) $tenant->getAttribute('ledger_payable'))->format()).'</span>')
            ->addColumn('receivable', fn (Tenant $tenant): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor((int) $tenant->getAttribute('ledger_receivable'))->format()).'</span>')
            ->addColumn('last_settlement', fn (Tenant $tenant): string => e($this->date($tenant->getAttribute('last_settlement_at'))))
            ->addColumn('status', function (Tenant $tenant): string {
                $status = FinancePageService::status((int) $tenant->getAttribute('ledger_receivable'), $this->nullableString($tenant->getAttribute('first_receivable_at')), $this->nullableString($tenant->getAttribute('last_settlement_at')));

                return view('billing::finance._badge', ['tone' => ['clear' => 'success', 'due' => 'warning', 'overdue' => 'danger'][$status], 'label' => __('billing::finance.ledger.status.'.$status)])->render();
            })
            ->addColumn('actions', fn (Tenant $tenant): string => view('billing::admin.finance.ledger._actions-cell', [
                'tenant' => $tenant,
                'canSettle' => $this->canManage && (int) $tenant->getAttribute('ledger_receivable') > 0,
                'canPayout' => $this->canPayout && (int) $tenant->getAttribute('ledger_payable') > 0,
            ])->render())
            ->rawColumns(['tenant', 'payable', 'receivable', 'status', 'actions']);
    }

    private function date(mixed $value): string
    {
        $value = $this->nullableString($value);

        return $value === null ? '—' : (string) Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('j M Y');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
