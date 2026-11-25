<?php

use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/BillingHelpers.php';

/*
| Invoices (ADM-054 / 054a): permissions, list + filters, detail + print view, and the
| invoice totals in Money (USD minor units).
*/

function issueInvoice(int $tenantId, array $lines, int $taxRateBp = 0, InvoiceType $type = InvoiceType::LanguageAddon): Invoice
{
    return app(InvoiceService::class)->issue(new InvoiceDraftData(
        tenantId: $tenantId,
        type: $type,
        lines: array_map(fn (array $line): InvoiceLineData => new InvoiceLineData(...$line), $lines),
        taxRateBp: $taxRateBp,
        billToName: 'Megz Trading Est.',
    ));
}

it('protects the invoice pages', function (): void {
    $invoice = Invoice::factory()->create(['tenant_id' => billingTenant()->id]);

    $this->get($this->centralUrl('/admin/finance/invoices'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(billingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/finance/invoices'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/finance/invoices/data'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/finance/invoices/{$invoice->id}"))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/finance/invoices/{$invoice->id}/print"))->assertForbidden();
});

it('computes invoice totals in USD minor units with VAT', function (): void {
    Event::fake([InvoiceIssued::class]);
    $tenant = billingTenant();

    $invoice = issueInvoice($tenant->id, [['Paid language: French (yearly)', 4261, 1], ['Setup', 333, 3]], 1500);

    // subtotal = 4261 + 3 × 333 = 5260; tax = 15 % of 52.60 = 7.89; total = 60.49
    expect($invoice->subtotal_minor)->toBe(5260)
        ->and($invoice->tax_minor)->toBe(789)
        ->and($invoice->total_minor)->toBe(6049)
        ->and($invoice->total()->toDecimal())->toBe('60.49')
        ->and($invoice->items->pluck('total_minor')->all())->toBe([4261, 999])
        ->and($invoice->number)->toBe(sprintf('INV-%d-%04d', now()->year, $invoice->id))
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid);

    Event::assertDispatched(InvoiceIssued::class);
});

it('uses the configured tax rate when none is given', function (): void {
    config(['billing.tax_rate_bp' => 500]);

    $invoice = app(InvoiceService::class)->issue(new InvoiceDraftData(
        tenantId: billingTenant()->id,
        type: InvoiceType::Manual,
        lines: [new InvoiceLineData('Service', 10000)],
    ));

    expect($invoice->tax_rate_bp)->toBe(500)
        ->and($invoice->tax_minor)->toBe(500)
        ->and($invoice->total_minor)->toBe(10500);
});

it('marks paid once, voids only unpaid invoices', function (): void {
    Event::fake([InvoicePaid::class]);
    $tenant = billingTenant();
    $service = app(InvoiceService::class);
    $invoice = issueInvoice($tenant->id, [['Line', 1000, 1]]);

    $service->markPaid($invoice, 'stripe', 'pi_123');
    $service->markPaid($invoice);

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->payment_reference)->toBe('pi_123');
    Event::assertDispatchedTimes(InvoicePaid::class, 1);

    expect(fn () => $service->void($invoice))->toThrow(BillingRuleException::class);

    $other = issueInvoice($tenant->id, [['Line', 1000, 1]]);
    $service->void($other);
    expect($other->status)->toBe(InvoiceStatus::Void)
        ->and(fn () => $service->markPaid($other))->toThrow(BillingRuleException::class);
});

it('lists invoices with status, type and search filters', function (): void {
    $this->actingAs(billingAdmin(['finance.invoices.view']), 'admin');
    $megz = billingTenant('Megz Store');
    $paid = issueInvoice($megz->id, [['Language', 4900, 1]]);
    app(InvoiceService::class)->markPaid($paid, 'stripe');
    issueInvoice(billingTenant('TechHub KSA')->id, [['Plan', 5900, 1]], type: InvoiceType::Subscription);

    $all = $this->getJson($this->centralUrl('/admin/finance/invoices/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($all['recordsTotal'])->toBe(2);

    expect($this->getJson($this->centralUrl('/admin/finance/invoices/data?draw=1&start=0&length=10&status=paid'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/finance/invoices/data?draw=1&start=0&length=10&type=subscription'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/finance/invoices/data?draw=1&start=0&length=10&search[value]=TechHub'))->json('recordsFiltered'))->toBe(1);

    $this->get($this->centralUrl('/admin/finance/invoices?status=unpaid'))->assertOk()->assertSee(route('admin.finance.invoices.data'), false);
});

it('shows the invoice detail and print view', function (): void {
    $this->actingAs(billingAdmin(['finance.invoices.view']), 'admin');
    $invoice = issueInvoice(billingTenant()->id, [['Paid language: French (yearly)', 4261, 1]], 1500);

    $this->get($this->centralUrl("/admin/finance/invoices/{$invoice->id}"))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('Paid language: French (yearly)')
        ->assertSee('Megz Trading Est.')
        ->assertSee('$49.00')
        ->assertSee(route('admin.finance.invoices.print', $invoice->id), false);

    $this->get($this->centralUrl("/admin/finance/invoices/{$invoice->id}/print"))
        ->assertOk()
        ->assertSee('data-autoprint', false)
        ->assertSee('$6.39');
});

it('answers 404 for an unknown invoice', function (): void {
    $this->actingAs(billingAdmin(['finance.invoices.view']), 'admin');

    $this->get($this->centralUrl('/admin/finance/invoices/999999'))->assertNotFound();
});
