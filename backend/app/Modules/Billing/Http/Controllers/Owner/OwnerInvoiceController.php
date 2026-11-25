<?php

namespace App\Modules\Billing\Http\Controllers\Owner;

use App\Modules\Billing\DataTables\OwnerInvoicesDataTable;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceQueryService;
use App\Modules\Billing\Services\OwnerBillingService;
use App\Modules\Identity\Models\Owner;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner portal › Invoices (OWN-005, Figma Affiliate & Owner 122:104137): invoices of
 * every owned store, detail and the shared print view (print / save as PDF).
 */
class OwnerInvoiceController extends Controller
{
    public function __construct(
        private readonly OwnerBillingService $billing,
        private readonly InvoiceQueryService $query,
    ) {}

    public function index(Request $request): View
    {
        return view('billing::owner.invoices.index', [
            'stores' => $this->billing->storeOptions($this->owner($request)),
            'statuses' => InvoiceStatus::options(),
        ]);
    }

    public function data(Request $request, OwnerInvoicesDataTable $table): JsonResponse
    {
        return $table->forOwner($this->owner($request)->id)->toJsonFromRequest($request, OwnerInvoicesDataTable::FILTERS);
    }

    public function show(Request $request, int $invoice): View
    {
        return view('billing::owner.invoices.show', ['invoice' => $this->owned($request, $invoice)]);
    }

    public function print(Request $request, int $invoice): View
    {
        return view('billing::owner.invoices.print', ['invoice' => $this->owned($request, $invoice)]);
    }

    private function owned(Request $request, int $id): Invoice
    {
        $invoice = $this->query->invoice($id);

        abort_unless($this->owner($request)->can('owner.invoices.view', $invoice), 403);

        return $invoice;
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
