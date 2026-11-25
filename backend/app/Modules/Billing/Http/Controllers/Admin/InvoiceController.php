<?php

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\DataTables\InvoicesDataTable;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Services\InvoiceQueryService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invoices (ADM-054 list, ADM-054a detail + print-optimised view). No PDF library is
 * installed, so "PDF" is the browser's print → Save as PDF on the print view.
 * Permission: finance.invoices.view.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceQueryService $query) {}

    public function index(): View
    {
        return view('billing::admin.invoices.index', [
            'counts' => $this->query->invoiceCounts(),
            'statuses' => InvoiceStatus::options(),
            'types' => InvoiceType::options(),
        ]);
    }

    public function data(Request $request, InvoicesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'type', 'search']);
    }

    public function show(int $invoice): View
    {
        return view('billing::admin.invoices.show', ['invoice' => $this->query->invoice($invoice)]);
    }

    public function print(int $invoice): View
    {
        return view('billing::admin.invoices.print', ['invoice' => $this->query->invoice($invoice)]);
    }
}
