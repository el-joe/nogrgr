<?php

namespace App\Modules\Billing\DataTables\Finance;

use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Support\LedgerPresenter;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Ledger transactions in two layouts:
 *  - `transactions` (ADM-053, Figma 74:63449): reference + source type, store, direction,
 *    source, amount, date, detail modal;
 *  - `entries` (ADM-050a one store, Figma 74:60018): date, entry, debit, credit, running
 *    balance (store side: credit raises what the platform owes it).
 *
 * @extends BaseDataTable<LedgerTransaction>
 */
final class TransactionsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'direction', 'source', 'tenant_id', 'from', 'to'];

    private string $layout = 'transactions';

    private ?int $tenantId = null;

    private string $surface = 'admin';

    public function __construct(private readonly LedgerRepositoryContract $ledger) {}

    public function forTenant(?int $tenantId, string $layout = 'entries', string $surface = 'admin'): self
    {
        $this->tenantId = $tenantId;
        $this->layout = $layout;
        $this->surface = $surface;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->ledger->listing([], $this->tenantId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->ledger->listing($filters, $this->tenantId)->with('tenant');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $byDate = static fn (Builder $query, string $order) => $query->orderBy('occurred_at', $direction($order))->orderBy('id', $direction($order));

        $table = $table
            ->filter(static fn (): null => null)
            ->orderColumn('date', $byDate)
            ->orderColumn('transaction', $byDate)
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->orderColumn('balance', $byDate)
            ->addColumn('date', fn (LedgerTransaction $transaction): string => '<span class="whitespace-nowrap text-ink" dir="ltr">'.e($transaction->occurred_at->locale(app()->getLocale())->translatedFormat('j M Y H:i')).'</span>');

        if ($this->layout === 'entries') {
            return $table
                ->addColumn('entry', fn (LedgerTransaction $transaction): string => '<span class="block font-medium text-ink">'.e(LedgerPresenter::title($transaction)).'</span><span class="text-xs text-ink-secondary">'.e(LedgerPresenter::subtitle($transaction)).'</span>')
                ->addColumn('debit', fn (LedgerTransaction $transaction): string => $transaction->net_amount < 0 ? '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor(-$transaction->net_amount)->format()).'</span>' : '')
                ->addColumn('credit', fn (LedgerTransaction $transaction): string => $transaction->net_amount > 0 ? '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor($transaction->net_amount)->format()).'</span>' : '')
                ->addColumn('balance', fn (LedgerTransaction $transaction): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor($transaction->balance_after)->format()).'</span>')
                ->rawColumns(['date', 'entry', 'debit', 'credit', 'balance']);
        }

        return $table
            ->addColumn('transaction', fn (LedgerTransaction $transaction): string => '<span class="block font-medium text-ink" dir="ltr">'.e((string) $transaction->reference).'</span><span class="text-xs text-ink-secondary">'.e($transaction->source->label()).'</span>')
            ->addColumn('store', fn (LedgerTransaction $transaction): string => view('billing::admin._store-cell', ['tenant' => $transaction->tenant])->render())
            ->addColumn('direction', fn (LedgerTransaction $transaction): string => view('billing::finance._badge', ['tone' => $transaction->direction->tone(), 'label' => $transaction->direction->label()])->render())
            ->addColumn('source', fn (LedgerTransaction $transaction): string => e(LedgerPresenter::title($transaction)))
            ->addColumn('amount', fn (LedgerTransaction $transaction): string => '<span class="font-semibold text-ink" dir="ltr">'.e($transaction->money()->format()).'</span>')
            ->addColumn('actions', fn (LedgerTransaction $transaction): string => view('billing::finance._view-action', [
                'modal' => 'transaction-modal',
                'url' => $this->surface === 'admin' ? route('admin.finance.transactions.show', $transaction->id) : '',
                'label' => __('billing::finance.transactions.view'),
            ])->render())
            ->rawColumns(['date', 'transaction', 'store', 'direction', 'amount', 'actions']);
    }
}
