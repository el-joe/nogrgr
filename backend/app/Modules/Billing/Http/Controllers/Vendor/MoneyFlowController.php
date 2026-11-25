<?php

namespace App\Modules\Billing\Http\Controllers\Vendor;

use App\Modules\Billing\DataTables\Finance\PayoutsDataTable;
use App\Modules\Billing\DataTables\Finance\SettlementsDataTable;
use App\Modules\Billing\DataTables\Finance\VendorPurchasesDataTable;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\RemoteModalPage;
use App\Shared\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Finance › Money flow (VEN-062 / 063 / 064, Figma 88:111108): purchases owed to
 * the platform (with Pay platform, CSV export), settlement payments (vendor → platform)
 * and payouts received (platform → store), one tab each, all read from the central
 * ledger / settlements / payouts of the current store.
 */
class MoneyFlowController extends Controller
{
    use RendersRemoteModals;

    public function __construct(private readonly FinancePageService $pages) {}

    public function purchases(): View
    {
        return $this->page('purchases');
    }

    public function purchasesData(Request $request, VendorPurchasesDataTable $table): JsonResponse
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $table->forTenant($this->tenantId(), $staff->can('orders.manage'))->toJsonFromRequest($request, VendorPurchasesDataTable::FILTERS);
    }

    public function purchasesExport(): StreamedResponse
    {
        $rows = VendorPurchasesDataTable::owedQuery($this->tenantId())->orderBy('created_at')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('billing::finance.money_flow.columns.order'), __('billing::finance.money_flow.columns.accrued'), __('billing::finance.money_flow.columns.settled'), __('billing::finance.money_flow.columns.owed'), __('billing::finance.money_flow.columns.since')], escape: '\\');

            foreach ($rows as $row) {
                fputcsv($out, [
                    '#'.$row->order_number,
                    Money::ofMinor((int) $row->getAttribute('accrued'))->toDecimal(),
                    Money::ofMinor((int) $row->getAttribute('settled'))->toDecimal(),
                    Money::ofMinor((int) $row->getAttribute('owed'))->toDecimal(),
                    (string) $row->created_at?->toDateString(),
                ], escape: '\\');
            }

            fclose($out);
        }, 'purchases-owed-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function settlements(): View
    {
        return $this->page('settlements');
    }

    public function settlementsData(Request $request, SettlementsDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId(), 'vendor')->toJsonFromRequest($request, SettlementsDataTable::FILTERS);
    }

    public function settlement(Request $request, string $reference): View
    {
        $rows = $this->pages->settlement($reference, $this->tenantId());

        return $this->remoteModal($request, 'billing::finance._settlement', ['rows' => $rows, 'attachmentUrl' => null], new RemoteModalPage('vendor', $reference, vendor_route('finance.settlements.index'), 'vendor-orders', null, null, [
            __('navigation.vendor.settlements') => vendor_route('finance.settlements.index'),
            $reference => null,
        ]));
    }

    public function payouts(): View
    {
        return $this->page('payouts');
    }

    public function payoutsData(Request $request, PayoutsDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId(), 'vendor')->toJsonFromRequest($request, PayoutsDataTable::FILTERS);
    }

    private function page(string $tab): View
    {
        return view('billing::vendor.finance.money-flow', [
            'tab' => $tab,
            'summary' => $this->pages->summary($this->tenantId()),
        ]);
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
