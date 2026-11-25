<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/RequestHelpers.php';

/*
| Requests center › Manufacturing (ADM-018e/f) and Brand requests (ADM-018g/h): list
| filters, detail (stepper, chat, notes), status modal and payment requests → Billing invoice.
*/

beforeEach(function (): void {
    [$this->tenant, $this->owner] = requestsStore();
    $this->manufacturing = ManufacturingRequest::factory()->create(['tenant_id' => $this->tenant->id, 'product_name' => 'Private-label perfume 50ml', 'status' => ManufacturingRequestStatus::Sampling]);
});

it('lists manufacturing requests filtered by status and store', function (): void {
    [$other] = requestsStore(['name' => 'Oud & Co']);
    ManufacturingRequest::factory()->create(['tenant_id' => $other->id, 'status' => ManufacturingRequestStatus::Delivered]);
    $this->actingAs(catalogAdmin(['requests.manufacturing.view']), 'admin');
    $url = fn (string $query = ''): string => $this->centralUrl('/admin/requests/manufacturing/data?draw=1&start=0&length=25'.$query);

    expect($this->getJson($url())->assertOk()->json('recordsFiltered'))->toBe(2)
        ->and($this->getJson($url('&status=open'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&tenant_id='.$other->id))->json('data.0.vendor'))->toContain('Oud &amp; Co')
        ->and(array_keys($this->getJson($url())->json('data.0')))->toContain('quantity');
});

it('renders the manufacturing detail with stepper, payments and notes', function (): void {
    $this->actingAs(catalogAdmin(['requests.manufacturing.manage']), 'admin')
        ->get($this->centralUrl("/admin/requests/manufacturing/{$this->manufacturing->id}"))
        ->assertOk()
        ->assertSee('Private-label perfume 50ml')
        ->assertSee('aria-current="step"', false)
        ->assertSee('payment-request-modal', false)
        ->assertSee(__('catalog::requests.notes.title'));
});

it('creates a payment request backed by a billing invoice and settles it when paid', function (): void {
    Notification::fake();
    $this->actingAs(catalogAdmin(['requests.manufacturing.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/manufacturing/{$this->manufacturing->id}/payments"), [
        'label' => 'Deposit 50%', 'amount' => '3125.50', 'due_at' => now()->addWeek()->toDateString(),
    ])->assertCreated();

    $payment = RequestPayment::query()->sole();
    $invoice = $payment->invoice;

    expect($payment->amount->minor)->toBe(312550)
        ->and($payment->status)->toBe(PaymentRequestStatus::Pending)
        ->and($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->tenant_id)->toBe($this->tenant->id)
        ->and($invoice->billable_type)->toBe('request_payment')
        ->and($invoice->billable_id)->toBe($payment->id)
        ->and($invoice->total_minor)->toBe(312550);
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'payment_requested');

    app(InvoiceService::class)->markPaid($invoice);

    expect($payment->refresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull();

    $this->postJson($this->centralUrl("/admin/requests/manufacturing/{$this->manufacturing->id}/payments/{$payment->id}/cancel"))->assertUnprocessable();
});

it('cancels a pending payment request and voids its invoice', function (): void {
    $brand = BrandRequest::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(catalogAdmin(['requests.brands.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/brands/{$brand->id}/payments"), ['label' => 'Logo design', 'amount' => 120])->assertCreated();
    $payment = RequestPayment::query()->sole();

    $this->postJson($this->centralUrl("/admin/requests/brands/{$brand->id}/payments/{$payment->id}/cancel"))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentRequestStatus::Cancelled)
        ->and($payment->invoice->status)->toBe(InvoiceStatus::Void);

    $this->actingAs(catalogAdmin(), 'admin');
    $this->postJson($this->centralUrl("/admin/requests/manufacturing/{$this->manufacturing->id}/payments/{$payment->id}/cancel"))->assertNotFound();
});

it('validates payment requests and guards them by permission', function (): void {
    $url = $this->centralUrl("/admin/requests/manufacturing/{$this->manufacturing->id}/payments");

    $this->actingAs(catalogAdmin(['requests.manufacturing.view']), 'admin');
    $this->postJson($url, ['label' => 'Deposit', 'amount' => 10])->assertForbidden();

    $this->actingAs(catalogAdmin(['requests.manufacturing.manage']), 'admin');
    $this->postJson($url, ['label' => '', 'amount' => 0, 'due_at' => now()->subDay()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['label', 'amount', 'due_at']);
    expect(RequestPayment::query()->count())->toBe(0);
});

it('changes the status and saves internal notes', function (): void {
    Notification::fake();
    $this->actingAs(catalogAdmin(['requests.manufacturing.manage']), 'admin');
    $base = "/admin/requests/manufacturing/{$this->manufacturing->id}";

    $this->patchJson($this->centralUrl($base.'/status'), ['status' => 'added_to_catalog'])->assertUnprocessable();
    $this->patchJson($this->centralUrl($base.'/status'), ['status' => 'awaiting_payment'])->assertOk();
    $this->putJson($this->centralUrl($base.'/notes'), ['admin_notes' => 'Supplier: Al-Fares, lead time 21 days'])->assertOk();

    expect($this->manufacturing->refresh()->status)->toBe(ManufacturingRequestStatus::AwaitingPayment)
        ->and($this->manufacturing->admin_notes)->toBe('Supplier: Al-Fares, lead time 21 days');
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'status_changed');
});

it('renders the brand list and detail pages', function (): void {
    $brand = BrandRequest::factory()->create(['tenant_id' => $this->tenant->id, 'brand_name' => 'Nile Crafts Home']);
    $this->actingAs(catalogAdmin(['requests.brands.view']), 'admin');

    $this->get($this->centralUrl('/admin/requests/brands'))->assertOk()->assertSee(route('admin.requests.brands.data'), false);
    $this->get($this->centralUrl("/admin/requests/brands/{$brand->id}"))->assertOk()->assertSee('Nile Crafts Home')->assertDontSee('aria-current="step"', false);
    $this->putJson($this->centralUrl("/admin/requests/brands/{$brand->id}/notes"), ['admin_notes' => 'x'])->assertForbidden();
});
