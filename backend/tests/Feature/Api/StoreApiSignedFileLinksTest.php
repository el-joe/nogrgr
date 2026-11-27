<?php

use App\Modules\Support\Models\CustomerTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| RQA-APP-016: Store API signed file links (customer-ticket attachments; return evidence is
| covered in StoreApiReturnsTest) carry their store in the signed `tenant` parameter, so the
| app opens them without X-Tenant (Image.network / in-app browser). Tampered, expired,
| store-less or other-store links are refused.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup('signedlinks');
    $this->customer = shopCustomer($this->shop);
    tenancy()->end();
    Sanctum::actingAs($this->customer, ['*'], 'sanctum');

    $response = $this->post(storeApi('support/tickets'), [
        'topic' => 'delivery',
        'subject' => 'Where is my parcel?',
        'body' => 'It was due yesterday and tracking has not moved.',
        'files' => [UploadedFile::fake()->image('parcel.jpg')],
    ], shopHeaders($this->shop->tenant))->assertCreated();

    $this->url = (string) $response->json('data.ticket.messages.0.attachments.0.url');
    $this->ticketId = (int) $response->json('data.ticket.id');
    $this->fileId = (int) $response->json('data.ticket.messages.0.attachments.0.id');
    // A browser / Image.network request: no X-Tenant, no bearer token.
    $this->app['auth']->forgetGuards();
});

it('opens the signed attachment link without X-Tenant', function (): void {
    expect($this->url)->toContain('tenant='.$this->shop->tenant->id)->toContain('signature=');

    $this->get($this->url)->assertOk();
});

it('refuses tampered, expired and store-less links', function (): void {
    $other = shopSetup('signedother');
    tenancy()->end();

    $this->get(str_replace('tenant='.$this->shop->tenant->id, 'tenant='.$other->tenant->id, $this->url))->assertNotFound();
    $this->get(str_replace('tenant='.$this->shop->tenant->id, 'tenant='.$other->tenant->id, $this->url), ['X-Tenant' => (string) $other->tenant->id])->assertForbidden();

    $this->travel(31)->minutes();
    $this->get($this->url)->assertNotFound();
    $this->get($this->url, ['X-Tenant' => (string) $this->shop->tenant->id])->assertForbidden();
    $this->travelBack();

    // Signed the old way (no tenant): never valid, whichever store the header names.
    $legacy = URL::temporarySignedRoute('store-api.v1.support.tickets.attachments', now()->addMinutes(30), ['ticket' => $this->ticketId, 'attachment' => $this->fileId], absolute: false);
    $this->get($legacy, ['X-Tenant' => (string) $this->shop->tenant->id])->assertForbidden();
    $this->get($legacy, ['X-Tenant' => (string) $other->tenant->id])->assertForbidden();
});

it('serves the signed store even when another store is named in X-Tenant', function (): void {
    $other = shopSetup('signedthird');
    tenancy()->end();

    // The header cannot redirect a store-A link to store B's ticket with the same ids.
    $other->tenant->run(fn () => expect(CustomerTicket::query()->find($this->ticketId))->toBeNull());
    $this->get($this->url, ['X-Tenant' => (string) $other->tenant->id])->assertOk();
});
