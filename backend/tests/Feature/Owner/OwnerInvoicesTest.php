<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Identity\Models\Owner;

require_once __DIR__.'/OwnerHelpers.php';

/*
| OWN-005 Invoices across all stores with view and download (print view).
*/

beforeEach(function (): void {
    $this->owner = Owner::factory()->create();
    $this->store = ownerStore($this->owner, ['name' => 'Oud House']);
    $this->invoice = ownerInvoice($this->store, ['number' => 'INV-OWN-0398']);
    $this->foreign = ownerInvoice(ownerStore(Owner::factory()->create()), ['number' => 'INV-FOREIGN-1']);
    $this->actingAs($this->owner, 'owner');
});

it('renders the invoices page', function (): void {
    $this->get(route('owner.invoices.index'))
        ->assertOk()
        ->assertSee(__('billing::owner.invoices.title'))
        ->assertSee(route('owner.invoices.data'), false);
});

it('lists only the owner\'s invoices, filterable by status', function (): void {
    ownerInvoice($this->store, ['number' => 'INV-OWN-PAID', 'status' => InvoiceStatus::Paid, 'paid_at' => now()]);

    $numbers = collect(ownerRows('owner.invoices.data'))->pluck('number')->implode(' ');
    expect($numbers)->toContain('INV-OWN-0398')->toContain('INV-OWN-PAID')->not->toContain('INV-FOREIGN-1');

    $paid = ownerRows('owner.invoices.data', ['status' => 'paid']);
    expect($paid)->toHaveCount(1)->and($paid[0]['number'])->toContain('INV-OWN-PAID');

    expect(ownerRows('owner.invoices.data', ['search' => 'INV-FOREIGN']))->toBe([]);
});

it('shows and prints an own invoice', function (): void {
    $this->get(route('owner.invoices.show', $this->invoice->id))
        ->assertOk()
        ->assertSee('INV-OWN-0398')
        ->assertSee(route('owner.invoices.print', $this->invoice->id), false);

    $this->get(route('owner.invoices.print', $this->invoice->id))
        ->assertOk()
        ->assertSee('INV-OWN-0398')
        ->assertSee('data-autoprint', false);
});

it('refuses another owner\'s invoice', function (): void {
    $this->get(route('owner.invoices.show', $this->foreign->id))->assertForbidden();
    $this->get(route('owner.invoices.print', $this->foreign->id))->assertForbidden();
    $this->get(route('owner.invoices.show', 999999))->assertNotFound();
});
