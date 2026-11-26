<?php

namespace App\Modules\Billing\Http\Controllers\Vendor;

use App\Modules\Billing\DataTables\VendorBillingInvoicesDataTable;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Billing\Services\VendorSubscriptionService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Finance › Invoices (VEN-061 / 061a, Figma Vendor Panel 88:110393): the
 * store's platform invoices (server-side list, filters status / type / issue date) with
 * the selected invoice in a side panel (VEN-061a, also a page of its own), print and
 * PDF (print-optimised sheet, decision #75 / #82) and Pay now for unpaid invoices
 * through the existing invoice checkout (SubscriptionController::checkout, action
 * invoice). Every lookup is pinned to the current store.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly VendorSubscriptionService $service,
        private readonly InvoiceCheckoutService $invoiceCheckout,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $this->tenantId();
        $selected = filter_var($request->query('invoice'), FILTER_VALIDATE_INT);

        return view('billing::vendor.invoices.index', [
            'statuses' => InvoiceStatus::options(),
            'types' => InvoiceType::options(),
            'canPay' => $this->canPay($request),
            'gateways' => $this->invoiceCheckout->gateways(),
            'selected' => is_int($selected) ? $this->service->invoice($tenantId, $selected) : null,
        ]);
    }

    public function data(Request $request, VendorBillingInvoicesDataTable $table): JsonResponse
    {
        return $table->forTenant($this->tenantId(), $this->canPay($request))
            ->toJsonFromRequest($request, VendorBillingInvoicesDataTable::FILTERS);
    }

    /**
     * VEN-061a: the detail fragment for the side panel (ajax) or the full page.
     */
    public function show(Request $request, int $invoice): View
    {
        $data = ['invoice' => $this->service->invoice($this->tenantId(), $invoice), 'canPay' => $this->canPay($request)];

        if ($request->ajax()) {
            return view('billing::vendor.invoices._detail', $data);
        }

        return view('billing::vendor.invoices.show', $data + ['gateways' => $this->invoiceCheckout->gateways()]);
    }

    /**
     * Print-optimised invoice sheet; the print dialog opens on load ("Save as PDF" there).
     */
    public function print(int $invoice): View
    {
        return view('billing::vendor.invoices.print', ['invoice' => $this->service->invoice($this->tenantId(), $invoice)]);
    }

    private function canPay(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('finance.subscription.manage');
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
