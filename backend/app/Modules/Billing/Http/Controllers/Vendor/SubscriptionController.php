<?php

namespace App\Modules\Billing\Http\Controllers\Vendor;

use App\Modules\Billing\DataTables\Finance\TransactionsDataTable;
use App\Modules\Billing\DataTables\VendorInvoicesDataTable;
use App\Modules\Billing\DataTables\VendorSubscriptionsDataTable;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionPageTab;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Http\Requests\Vendor\SubscriptionCheckoutRequest;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Billing\Services\VendorSubscriptionService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Finance › Subscription & balance (VEN-060…060d, Figma Vendor Panel 88:109601
 * / 88:113398): overview (balance cards, current plan, usage vs limits, latest
 * transactions), subscriptions history + invoices, central ledger transactions; renew /
 * change plan / pay invoice → hosted checkout → result page.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly VendorSubscriptionService $service,
        private readonly SubscriptionRenewalService $renewals,
        private readonly InvoiceCheckoutService $invoiceCheckout,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId();
        $current = $this->service->current($tenantId);

        return view('billing::vendor.subscription.index', [
            'tab' => SubscriptionPageTab::fromQuery($request->query('tab')),
            'subscription' => $current,
            'plans' => $this->service->plans(),
            'usage' => $this->service->usage($current?->plan),
            'balance' => $this->service->balance($tenantId),
            'recent' => $this->service->recentTransactions($tenantId),
            'unpaidInvoice' => $this->service->unpaidInvoice($tenantId),
            'renewable' => $current !== null && $this->renewals->renewable($current),
            'gateways' => $this->renewals->gateways(),
            'invoiceGateways' => $this->invoiceCheckout->gateways(),
            'canManage' => $this->staff($request)->can('finance.subscription.manage'),
            'subscriptionStatuses' => SubscriptionStatus::options(),
            'invoiceStatuses' => InvoiceStatus::options(),
        ]);
    }

    public function historyData(Request $request, VendorSubscriptionsDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId())->toJsonFromRequest($request, VendorSubscriptionsDataTable::FILTERS);
    }

    public function invoicesData(Request $request, VendorInvoicesDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId(), $this->staff($request)->can('finance.subscription.manage'))
            ->toJsonFromRequest($request, VendorInvoicesDataTable::FILTERS);
    }

    public function transactionsData(Request $request, TransactionsDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId(), 'entries', 'vendor')
            ->toJsonFromRequest($request, array_values(array_diff(TransactionsDataTable::FILTERS, ['tenant_id'])));
    }

    public function printInvoice(int $invoice): View
    {
        return view('billing::vendor.subscription.invoice-print', ['invoice' => $this->service->invoice($this->tenantId(), $invoice)]);
    }

    public function checkout(SubscriptionCheckoutRequest $request): RedirectResponse
    {
        try {
            $checkout = $this->service->checkout($this->tenantId(), $request->toData(), $this->staff($request), static fn (PaymentLog $log): array => [
                'success' => vendor_route('finance.subscription.result', ['payment' => $log->id]),
                'cancel' => vendor_route('finance.subscription.cancel', ['payment' => $log->id]),
            ]);
        } catch (BillingRuleException $exception) {
            return redirect()->to(vendor_route('finance.subscription'))->with('error', $exception->getMessage());
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return redirect()->to(vendor_route('finance.subscription'))->with('error', __('billing::vendor_subscription.errors.gateway'));
        }

        return redirect()->away($checkout['redirect']);
    }

    /**
     * Gateway success URL: verify (captures) and show the outcome.
     */
    public function result(int $payment): View
    {
        return $this->resultPage($this->service->settle($this->paymentLog($payment), false), false);
    }

    /**
     * Gateway cancel URL (a checkout paid after all is still captured).
     */
    public function cancel(int $payment): View
    {
        return $this->resultPage($this->service->settle($this->paymentLog($payment), true), true);
    }

    private function resultPage(PaymentLog $log, bool $returnedFromCancel): View
    {
        return view('billing::vendor.subscription.result', [
            'log' => $log,
            'returnedFromCancel' => $returnedFromCancel,
        ] + $this->service->resultDetails($log));
    }

    private function paymentLog(int $payment): PaymentLog
    {
        return $this->service->paymentLog($this->tenantId(), $payment) ?? abort(404);
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
