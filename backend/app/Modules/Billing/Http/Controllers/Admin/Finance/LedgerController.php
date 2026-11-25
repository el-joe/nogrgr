<?php

namespace App\Modules\Billing\Http\Controllers\Admin\Finance;

use App\Modules\Billing\DataTables\Finance\LedgerTenantsDataTable;
use App\Modules\Billing\DataTables\Finance\TransactionsDataTable;
use App\Modules\Billing\DTOs\Finance\FinanceActor;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Billing\Http\Requests\Finance\MarkSettlementPaidRequest;
use App\Modules\Billing\Http\Requests\Finance\StoreSearchRequest;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Billing\Services\Finance\ManualSettlementService;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use App\Shared\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Finance › Ledger (ADM-050 / 050a, Figma 74:59026 · 74:60018 · 74:68877): KPI
 * cards, balances per store, a store's ledger entries with running balance, statement
 * (print / PDF), CSV export and the "mark settlement paid" modal.
 */
class LedgerController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FinancePageService $pages,
        private readonly ManualSettlementService $settlements,
        private readonly LedgerRepositoryContract $ledger,
        private readonly CountryRepositoryContract $countries,
    ) {}

    public function index(): View
    {
        return view('billing::admin.finance.ledger.index', [
            'stats' => $this->pages->ledgerStats(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, LedgerTenantsDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->withAbilities($admin->can('finance.ledger.manage'), $admin->can('finance.payouts.manage'))->toJsonFromRequest($request, LedgerTenantsDataTable::FILTERS);
    }

    public function stores(StoreSearchRequest $request): JsonResponse
    {
        return response()->json($this->pages->storeSearch($request->term(), $request->page()));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->pages->ledgerQuery($request->only(LedgerTenantsDataTable::FILTERS))->orderBy('tenants.name');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('billing::finance.ledger.columns.tenant'), __('billing::finance.ledger.columns.payable'), __('billing::finance.ledger.columns.receivable'), __('billing::finance.ledger.net'), __('billing::finance.ledger.columns.last_settlement'), __('billing::finance.ledger.columns.status')], escape: '\\');

            foreach ($query->cursor() as $tenant) {
                $payable = (int) $tenant->getAttribute('ledger_payable');
                $receivable = (int) $tenant->getAttribute('ledger_receivable');
                $last = $tenant->getAttribute('last_settlement_at');
                $status = FinancePageService::status($receivable, is_string($tenant->getAttribute('first_receivable_at')) ? $tenant->getAttribute('first_receivable_at') : null, is_string($last) ? $last : null);

                fputcsv($out, [
                    $tenant->getAttribute('name'),
                    Money::ofMinor($payable)->toDecimal(),
                    Money::ofMinor($receivable)->toDecimal(),
                    Money::ofMinor($payable - $receivable)->toDecimal(),
                    is_string($last) ? $last : '',
                    __('billing::finance.ledger.status.'.$status),
                ], escape: '\\');
            }

            fclose($out);
        }, 'ledger-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, int $tenant): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $store = $this->pages->tenant($tenant);

        return view('billing::admin.finance.ledger.show', [
            'tenant' => $store,
            'summary' => $this->pages->summary($tenant),
            'canSettle' => $admin->can('finance.ledger.manage'),
            'canPayout' => $admin->can('finance.payouts.manage'),
        ]);
    }

    public function entries(Request $request, int $tenant, TransactionsDataTable $table): JsonResponse
    {
        $this->pages->tenant($tenant);

        return $table->forTenant($tenant)->toJsonFromRequest($request, TransactionsDataTable::FILTERS);
    }

    public function statement(int $tenant): View
    {
        $store = $this->pages->tenant($tenant);

        return view('billing::admin.finance.ledger.statement', [
            'tenant' => $store,
            'summary' => $this->pages->summary($tenant),
            'transactions' => $this->ledger->listing([], $tenant)->orderBy('occurred_at')->orderBy('id')->limit(1000)->get(),
        ]);
    }

    public function settleForm(Request $request, int $tenant): View
    {
        $store = $this->pages->tenant($tenant);
        $summary = $this->pages->summary($tenant);

        return $this->remoteModal($request, 'billing::admin.finance.ledger._settle-form', [
            'tenant' => $store,
            'summary' => $summary,
            'methods' => SettlementMethod::options(),
            'formAction' => route('admin.finance.ledger.settle', $tenant),
        ], new RemoteModalPage('admin', (string) __('billing::finance.settle.title'), route('admin.finance.ledger.show', $tenant), 'admin-finance', null, null, [
            __('navigation.admin.finance') => null,
            __('navigation.admin.ledger') => route('admin.finance.ledger.index'),
            $store->name => route('admin.finance.ledger.show', $tenant),
            __('billing::finance.settle.title') => null,
        ]));
    }

    public function settle(MarkSettlementPaidRequest $request, int $tenant): JsonResponse
    {
        $this->pages->tenant($tenant);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $rows = $this->settlements->markPaid($tenant, $request->toData(), new FinanceActor($admin->id, $admin->name));

        return JsonResponder::success((string) __('billing::finance.settle.done', ['reference' => (string) $rows->first()?->reference]), null, route('admin.finance.ledger.show', $tenant));
    }
}
