<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Support\Str;

require_once __DIR__.'/BillingHelpers.php';

/*
| VEN-061 / 061a Vendor › Finance › Invoices: the store's platform invoices (filters
| status / type / issue date / search), the detail panel + page, print / PDF sheet, Pay
| now for unpaid invoices (finance.subscription.manage), permissions and isolation.
*/

beforeEach(function (): void {
    $this->store = $this->createTenant('inv-'.Str::lower(Str::random(6)), ['name' => 'Megz Store']);
    $this->owner = $this->createVendorOwner($this->store);
    $this->other = billingTenant('Other Store');
    $this->url = fn (string $path = '') => $this->tenantUrl($this->store, 'admin/finance/invoices'.($path === '' ? '' : '/'.$path));
    $this->dt = fn (string $query = '') => $this->getJson(($this->url)('data?draw=1&start=0&length=25'.$query))->assertOk()->json();
    $this->invoice = function (array $attributes = [], ?string $line = null): Invoice {
        $invoice = Invoice::factory()->create(array_merge(['tenant_id' => $this->store->id], $attributes));

        if ($line !== null) {
            $invoice->items()->create(['description' => $line, 'quantity' => 1, 'unit_amount_minor' => $invoice->subtotal_minor, 'total_minor' => $invoice->subtotal_minor, 'sort_order' => 1]);
        }

        return $invoice;
    };
});

it('sends guests to the login and needs finance.invoices.view for the list', function (): void {
    $invoice = ($this->invoice)();
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->store, 'login'));

    [$none, $subscriptionViewer] = $this->store->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'finance.subscription.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->store);
    foreach (['', 'data', (string) $invoice->id, "{$invoice->id}/print"] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }

    // The subscription page links to the detail and print of its invoices.
    $this->actingAsVendor($subscriptionViewer, $this->store);
    $this->getJson(($this->url)())->assertForbidden();
    $this->get(($this->url)((string) $invoice->id))->assertOk()->assertSee($invoice->number);
    $this->get(($this->url)("{$invoice->id}/print"))->assertOk()->assertSee('data-autoprint', false);
});

it('lists only the store\'s invoices with status, type, date and search filters', function (): void {
    ($this->invoice)(['number' => 'INV-2026-1186', 'type' => InvoiceType::LanguageAddon, 'status' => InvoiceStatus::Paid, 'issued_at' => now()->subDays(2)], 'Paid language: French (yearly)');
    ($this->invoice)(['number' => 'INV-2026-0311', 'type' => InvoiceType::Subscription, 'status' => InvoiceStatus::Unpaid, 'issued_at' => now()->subMonths(3)], 'Growth yearly');
    Invoice::factory()->create(['tenant_id' => $this->other->id, 'number' => 'INV-FOREIGN-01']);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())->assertOk()->assertSee(__('billing::vendor_invoices.subtitle'))->assertSee(__('billing::vendor_invoices.detail.select_title'));

    $all = ($this->dt)();
    expect($all['recordsTotal'])->toBe(2)
        ->and(collect($all['data'])->pluck('number')->implode(' '))->toContain('INV-2026-1186')->toContain('French')->not->toContain('INV-FOREIGN-01');

    expect(($this->dt)('&status=paid')['data'])->toHaveCount(1)
        ->and(($this->dt)('&type=subscription')['data'][0]['number'])->toContain('INV-2026-0311')
        ->and(($this->dt)('&from='.now()->subWeek()->toDateString().'&to='.now()->toDateString())['data'][0]['number'])->toContain('INV-2026-1186')
        ->and(($this->dt)('&search=0311')['recordsFiltered'])->toBe(1)
        ->and(($this->dt)('&search=Other')['recordsFiltered'])->toBe(0)
        ->and(($this->dt)('&from=not-a-date')['recordsFiltered'])->toBe(2);
});

it('shows the invoice detail in the panel, on its page and preselected; print / PDF sheet', function (): void {
    $invoice = ($this->invoice)(['number' => 'INV-2026-1171', 'subtotal_minor' => 4261, 'tax_rate_bp' => 1500, 'tax_minor' => 639, 'total_minor' => 4900], 'AI translation add-on');
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)((string) $invoice->id), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
        ->assertSee('data-invoice-detail', false)->assertSee('AI translation add-on')->assertSee('$49.00')->assertSee('$6.39')
        ->assertDontSee('<html', false);
    $this->get(($this->url)((string) $invoice->id))->assertOk()->assertSee('INV-2026-1171')->assertSee(__('billing::vendor_invoices.actions.back'));
    $this->get(($this->url)().'?invoice='.$invoice->id)->assertOk()->assertSee('data-invoice-detail="'.$invoice->id.'"', false);
    $this->get(($this->url)("{$invoice->id}/print?save=1"))->assertOk()->assertSee('INV-2026-1171')->assertSee('data-autoprint', false);
});

it('offers Pay now on unpaid invoices only with finance.subscription.manage', function (): void {
    $unpaid = ($this->invoice)(['status' => InvoiceStatus::Unpaid]);
    $viewer = $this->store->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'finance.invoices.view']);

        return $staff;
    });

    $this->actingAsVendor($this->owner, $this->store);
    expect(($this->dt)()['data'][0]['actions'])->toContain('data-invoice-pay')->toContain(($this->url)("{$unpaid->id}/print"));
    $this->get(($this->url)())->assertSee('invoice-pay-modal', false);

    $this->actingAsVendor($viewer, $this->store);
    expect(($this->dt)()['data'][0]['actions'])->not->toContain('data-invoice-pay');
    $this->get(($this->url)())->assertDontSee('invoice-pay-modal', false);
});

it('never opens another store\'s invoice', function (): void {
    $foreign = Invoice::factory()->create(['tenant_id' => $this->other->id]);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)((string) $foreign->id))->assertNotFound();
    $this->get(($this->url)("{$foreign->id}/print"))->assertNotFound();
    $this->get(($this->url)().'?invoice='.$foreign->id)->assertNotFound();
});
