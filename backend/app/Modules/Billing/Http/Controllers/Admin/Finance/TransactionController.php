<?php

namespace App\Modules\Billing\Http\Controllers\Admin\Finance;

use App\Modules\Billing\DataTables\Finance\TransactionsDataTable;
use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Http\Requests\Finance\StoreSearchRequest;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Billing\Support\LedgerPresenter;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\RemoteModalPage;
use App\Shared\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Finance › Transactions (ADM-053, Figma 74:63449 · 74:68877): every ledger
 * transaction with direction / source / store filters, CSV export and the detail modal
 * (direction, source, store, amount, its debit / credit lines).
 */
class TransactionController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FinancePageService $pages,
        private readonly LedgerRepositoryContract $ledger,
    ) {}

    public function index(): View
    {
        return view('billing::admin.finance.transactions.index', [
            'directions' => LedgerDirection::options(),
            'sources' => LedgerSource::options(),
        ]);
    }

    public function data(Request $request, TransactionsDataTable $table): JsonResponse
    {
        return $table->forTenant(null, 'transactions')->toJsonFromRequest($request, TransactionsDataTable::FILTERS);
    }

    public function stores(StoreSearchRequest $request): JsonResponse
    {
        return response()->json($this->pages->storeSearch($request->term(), $request->page()));
    }

    public function show(Request $request, int $transaction): View
    {
        $row = $this->pages->transaction($transaction);

        return $this->remoteModal($request, 'billing::finance._transaction', ['transaction' => $row], new RemoteModalPage('admin', (string) $row->reference, route('admin.finance.transactions.index'), 'admin-finance', null, null, [
            __('navigation.admin.finance') => null,
            __('navigation.admin.transactions') => route('admin.finance.transactions.index'),
            (string) $row->reference => null,
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->ledger->listing($request->only(TransactionsDataTable::FILTERS))->with('tenant')->orderByDesc('occurred_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('billing::finance.transactions.columns.transaction'), __('billing::finance.transactions.columns.store'), __('billing::finance.transactions.columns.direction'), __('billing::finance.transactions.columns.source'), __('billing::finance.transactions.columns.amount'), __('billing::finance.transactions.columns.date')], escape: '\\');

            foreach ($query->lazy(500) as $row) {
                fputcsv($out, [
                    (string) $row->reference,
                    (string) $row->tenant?->getAttribute('name'),
                    $row->direction->label(),
                    LedgerPresenter::title($row),
                    Money::ofMinor($row->amount)->toDecimal(),
                    $row->occurred_at->toDateTimeString(),
                ], escape: '\\');
            }

            fclose($out);
        }, 'transactions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
