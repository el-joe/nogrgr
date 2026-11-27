<?php

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Jobs\SendCustomerPush;
use App\Modules\Notifications\Listeners\QueueCustomerPush;
use App\Modules\Notifications\Services\CustomerPushService;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketAttachment;
use App\Modules\Support\Notifications\CustomerTicketNotification;
use App\Modules\Support\Notifications\StoreSupportNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';
require_once __DIR__.'/SupportHelpers.php';

/*
| Customer ↔ store support tickets: Store API (app + theme) list / options / open / show /
| reply / close / files (auth, validation, own tickets only, other store isolation), the
| account Support page in every family, Vendor › Support › Customers (permissions, list,
| thread, reply, status), and the notifications both ways (customer inbox + mail + push,
| store staff bell).
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup('custsup');
    $this->tenant = $this->shop->tenant;
    $this->customer = shopCustomer($this->shop);
    $this->other = shopCustomer($this->shop);
    $this->staff = $this->createVendorOwner($this->tenant, ['name' => 'Mona Khaled', 'email' => 'mona@custsup.test']);
    tenancy()->end();
    $this->headers = shopHeaders($this->tenant);
    $this->payload = fn (array $overrides = []): array => array_replace([
        'topic' => 'delivery',
        'subject' => 'Where is my parcel?',
        'body' => 'It was due yesterday and tracking has not moved.',
    ], $overrides);
    $this->open = function (array $overrides = [], ?Customer $as = null): CustomerTicket {
        Sanctum::actingAs($as ?? $this->customer, ['*'], 'sanctum');
        $id = $this->post(storeApi('support/tickets'), ($this->payload)($overrides), $this->headers)->assertCreated()->json('data.ticket.id');

        return $this->tenant->run(fn () => CustomerTicket::query()->findOrFail($id));
    };
    $this->vendorUrl = fn (string $path = ''): string => $this->tenantUrl($this->tenant, 'admin/support/customers'.$path);
});

it('requires a signed-in customer on every endpoint', function (): void {
    $this->getJson(storeApi('support/tickets'), $this->headers)->assertUnauthorized()->assertJsonPath('success', false);
    $this->getJson(storeApi('support/tickets/options'), $this->headers)->assertUnauthorized();
    $this->postJson(storeApi('support/tickets'), ($this->payload)(), $this->headers)->assertUnauthorized();
    $this->getJson(storeApi('support/tickets/1'), $this->headers)->assertUnauthorized();
    $this->postJson(storeApi('support/tickets/1/messages'), ['body' => 'Hi'], $this->headers)->assertUnauthorized();
    $this->postJson(storeApi('support/tickets/1/close'), [], $this->headers)->assertUnauthorized();
});

it('validates a new ticket', function (): void {
    Sanctum::actingAs($this->customer, ['*'], 'sanctum');

    $this->postJson(storeApi('support/tickets'), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic', 'subject', 'body']);

    $this->postJson(storeApi('support/tickets'), ($this->payload)(['topic' => 'nope', 'order_uuid' => 'x']), $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic', 'order_uuid']);

    $this->post(storeApi('support/tickets'), ($this->payload)(['files' => [UploadedFile::fake()->create('virus.exe', 10)]]), $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');

    // An order that is not the customer's.
    $this->postJson(storeApi('support/tickets'), ($this->payload)(['order_uuid' => '7f0c7d3e-1a7c-4d7e-9f00-2b51a8f4a001']), $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('order_uuid');
});

it('opens a ticket with files, notifies the store staff and lists it for its owner only', function (): void {
    Sanctum::actingAs($this->customer, ['*'], 'sanctum');

    $this->getJson(storeApi('support/tickets/options'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.max_files', 5)
        ->assertJsonFragment(['value' => 'delivery']);

    $response = $this->post(storeApi('support/tickets'), ($this->payload)(['files' => [UploadedFile::fake()->image('parcel.jpg')]]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ticket.status.value', 'open')
        ->assertJsonPath('data.ticket.topic.value', 'delivery')
        ->assertJsonPath('data.ticket.messages.0.author', 'customer')
        ->assertJsonPath('data.ticket.messages.0.attachments.0.name', 'parcel.jpg');

    $reference = $response->json('data.ticket.reference');
    expect($reference)->toStartWith('C-');

    $this->tenant->run(function (): void {
        $file = CustomerTicketAttachment::query()->firstOrFail();
        Storage::disk('central')->assertExists($file->path);
        expect($file->path)->toStartWith('customer-tickets/'.$this->tenant->id.'/');
        Notification::assertSentTo($this->staff, StoreSupportNotification::class, fn (StoreSupportNotification $notification): bool => $notification->eventName === 'customer_opened' && $notification->vendorRoute === 'support.customers.show');
    });

    // The signed file link works; a tampered one does not.
    $url = $response->json('data.ticket.messages.0.attachments.0.url');
    $this->get($url, $this->headers)->assertOk();
    $this->get($url.'x', $this->headers)->assertForbidden();

    $this->getJson(storeApi('support/tickets'), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.reference', $reference)
        ->assertJsonPath('data.meta.total', 1);
    $this->getJson(storeApi('support/tickets?status=closed'), $this->headers)->assertOk()->assertJsonCount(0, 'data.items');
    $this->getJson(storeApi('support/tickets?status=bogus'), $this->headers)->assertUnprocessable();

    Sanctum::actingAs($this->other, ['*'], 'sanctum');
    $id = $response->json('data.ticket.id');
    $this->getJson(storeApi('support/tickets'), $this->headers)->assertOk()->assertJsonCount(0, 'data.items');
    $this->getJson(storeApi("support/tickets/{$id}"), $this->headers)->assertNotFound();
    $this->postJson(storeApi("support/tickets/{$id}/messages"), ['body' => 'Mine?'], $this->headers)->assertNotFound();
    $this->postJson(storeApi("support/tickets/{$id}/close"), [], $this->headers)->assertNotFound();
});

it('keeps tickets inside their store', function (): void {
    $ticket = ($this->open)();
    $beta = shopSetup('custsupbeta');
    $stranger = shopCustomer($beta);
    tenancy()->end();

    Sanctum::actingAs($stranger, ['*'], 'sanctum');
    $this->getJson(storeApi("support/tickets/{$ticket->id}"), shopHeaders($beta->tenant))->assertNotFound();

    $betaStaff = $this->createVendorOwner($beta->tenant);
    $this->actingAsVendor($betaStaff, $beta->tenant)->get($this->tenantUrl($beta->tenant, "admin/support/customers/{$ticket->id}"))->assertNotFound();
});

it('links an own order to the ticket', function (): void {
    shopGateway($this->tenant);
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);
    tenancy()->end();

    $ticket = ($this->open)(['order_uuid' => $order->uuid, 'topic' => 'order_issue']);

    expect($ticket->order_uuid)->toBe($order->uuid)->and($ticket->order_number)->toBe($order->number);
});

it('runs the conversation both ways with notifications, push and status rules', function (): void {
    $ticket = ($this->open)();

    // Store staff reply from the vendor panel → answered, customer inbox + mail + push.
    $this->actingAsVendor($this->staff, $this->tenant)
        ->postJson(($this->vendorUrl)("/{$ticket->id}/messages"), ['body' => 'It ships today, sorry for the wait.'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $this->tenant->run(function () use ($ticket): void {
        expect($ticket->fresh()->status)->toBe(TicketStatus::Answered)->and($ticket->fresh()->customer_unread)->toBeTrue();
        Notification::assertSentTo($this->customer, CustomerTicketNotification::class, function (CustomerTicketNotification $notification) use ($ticket): bool {
            $data = $notification->toArray($this->customer);

            return $notification->eventName === 'ticket_replied'
                && $data['ticket_id'] === $ticket->id
                && in_array('mail', $notification->via($this->customer), true)
                && str_contains((string) $notification->ticketUrl, '/account/support/'.$ticket->id);
        });
    });

    // The customer reads it (unread cleared) and replies.
    Sanctum::actingAs($this->customer, ['*'], 'sanctum');
    $this->getJson(storeApi("support/tickets/{$ticket->id}"), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.ticket.status.value', 'answered')
        ->assertJsonPath('data.ticket.messages.1.author', 'store')
        ->assertJsonCount(2, 'data.ticket.messages');
    $this->tenant->run(fn () => expect($ticket->fresh()->customer_unread)->toBeFalse());

    $this->postJson(storeApi("support/tickets/{$ticket->id}/messages"), [], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson(storeApi("support/tickets/{$ticket->id}/messages"), ['body' => 'Thanks!'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.message.author', 'customer')
        ->assertJsonPath('data.ticket.status.value', 'open');

    // Staff resolve → customer told; a customer message reopens it.
    $this->actingAsVendor($this->staff, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/status/resolved"))->assertOk();
    $this->tenant->run(fn () => expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved));
    Sanctum::actingAs($this->customer, ['*'], 'sanctum');
    $this->postJson(storeApi("support/tickets/{$ticket->id}/messages"), ['body' => 'One more thing'], $this->headers)->assertCreated()->assertJsonPath('data.ticket.status.value', 'open');

    // Customer close → staff told; no more customer messages; staff can reopen.
    $this->postJson(storeApi("support/tickets/{$ticket->id}/close"), [], $this->headers)->assertOk()->assertJsonPath('data.ticket.status.value', 'closed');
    $this->postJson(storeApi("support/tickets/{$ticket->id}/close"), [], $this->headers)->assertUnprocessable()->assertJsonPath('success', false);
    $this->postJson(storeApi("support/tickets/{$ticket->id}/messages"), ['body' => 'Hello?'], $this->headers)->assertUnprocessable();

    $this->tenant->run(function (): void {
        Notification::assertSentTo($this->staff, StoreSupportNotification::class, fn (StoreSupportNotification $notification): bool => $notification->eventName === 'customer_closed');
        Notification::assertSentTo($this->customer, CustomerTicketNotification::class, fn (CustomerTicketNotification $notification): bool => $notification->eventName === 'ticket_resolved');
    });

    $this->actingAsVendor($this->staff, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/status/open"))->assertOk();
    $this->actingAsVendor($this->staff, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/status/open"))->assertUnprocessable();
    $this->actingAsVendor($this->staff, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/status/bogus"))->assertNotFound();
});

it('pushes store replies to the customer app as support notifications', function (): void {
    $ticket = ($this->open)();
    $this->tenant->run(function () use ($ticket): void {
        $notification = new CustomerTicketNotification('ticket_replied', $ticket->id, ['reference' => 'C-1001', 'subject' => 'Parcel', 'store' => 'Shop', 'excerpt' => 'Hi', 'author' => 'Mona']);
        $payload = $notification->toArray($this->customer);

        expect(CustomerPushService::typeOf($payload['event'])->value)->toBe('support')
            ->and(__($payload['title_key'], $payload['params']))->toBe('New reply on your ticket C-1001');
    });

    expect(class_exists(QueueCustomerPush::class) && class_exists(SendCustomerPush::class))->toBeTrue();
});

it('gates Vendor › Support › Customers by the support permissions', function (): void {
    $ticket = ($this->open)();

    $this->get(($this->vendorUrl)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $nobody = supportStaff($this->tenant, []);
    $this->actingAsVendor($nobody, $this->tenant)->get(($this->vendorUrl)())->assertForbidden();
    $this->actingAsVendor($nobody, $this->tenant)->getJson(($this->vendorUrl)('/data'))->assertForbidden();

    $viewer = supportStaff($this->tenant, ['support.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->vendorUrl)("/{$ticket->id}"))
        ->assertOk()
        ->assertSee('data-page="vendor-support-customers"', false)
        ->assertSee($ticket->subject)
        ->assertSee(__('support::tickets.thread.read_only'));
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/messages"), ['body' => 'Hi'])->assertForbidden();
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->vendorUrl)("/{$ticket->id}/status/closed"))->assertForbidden();
});

it('lists, opens and serves files of customer tickets for the store staff', function (): void {
    $ticket = ($this->open)(['files' => [UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf')]]);
    $as = fn () => $this->actingAsVendor($this->staff, $this->tenant);

    $as()->get(($this->vendorUrl)())
        ->assertOk()
        ->assertSee(__('support::customer_tickets.tabs.customers'))
        ->assertSee('vendor-customer-tickets', false)
        ->assertSee('It was due yesterday');
    $this->tenant->run(fn () => expect($ticket->fresh()->store_unread)->toBeFalse());

    $rows = $as()->getJson(($this->vendorUrl)('/data?draw=1&start=0&length=10&search[value]=parcel'))->assertOk()->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['ticket'])->toContain($ticket->displayReference());
    expect($as()->getJson(($this->vendorUrl)('/data?draw=1&start=0&length=10&status=closed'))->json('data'))->toHaveCount(0);

    $as()->getJson(($this->vendorUrl)("/{$ticket->id}/messages?after=0"))->assertOk()->assertJsonPath('data.last_id', fn ($id) => $id > 0);

    $file = $this->tenant->run(fn () => CustomerTicketAttachment::query()->firstOrFail());
    $as()->get(($this->vendorUrl)("/attachments/{$file->id}"))->assertOk();
    $as()->get(($this->vendorUrl)('/attachments/999999'))->assertNotFound();

    // Platform tab still there with the new tab row.
    $as()->get($this->tenantUrl($this->tenant, 'admin/support'))->assertOk()->assertSee(__('support::customer_tickets.tabs.customers'));
});

it('shows the empty state without customer tickets', function (): void {
    $this->actingAsVendor($this->staff, $this->tenant)->get(($this->vendorUrl)())
        ->assertOk()
        ->assertSee(__('support::customer_tickets.empty.title'));
});

it('renders the account Support pages in every theme family', function (?string $theme, string $family): void {
    if ($theme !== null) {
        $this->seed(ThemeCatalogSeeder::class);
        $this->tenant->run(fn () => app(StoreThemeService::class)->activate($theme));
    }

    $ticket = ($this->open)();
    tenancy()->end();

    $this->actingAsCustomer($this->customer, $this->tenant)
        ->get($this->tenantUrl($this->tenant, '/account/support'))
        ->assertOk()
        ->assertViewIs("storefront::themes.{$family}.pages.support.index")
        ->assertSee('data-ticket-row', false)
        ->assertSee('sf-new-ticket', false)
        ->assertSee(route('store-api.v1.support.tickets.store', [], false), false);

    $this->actingAsCustomer($this->customer, $this->tenant)
        ->get($this->tenantUrl($this->tenant, "/account/support/{$ticket->id}"))
        ->assertOk()
        ->assertViewIs("storefront::themes.{$family}.pages.support.show")
        ->assertSee('data-ticket-message="customer"', false)
        ->assertSee('sf-close-ticket', false);

    $this->actingAsCustomer($this->other, $this->tenant)->get($this->tenantUrl($this->tenant, "/account/support/{$ticket->id}"))->assertNotFound();
})->with([
    'ecommet' => [null, 'ecommet'],
    'elora' => ['elora-1', 'elora'],
    'souqify' => ['souqify-1', 'souqify'],
]);

it('sends guests from the account Support pages to sign in', function (): void {
    $this->get($this->tenantUrl($this->tenant, '/account/support'))->assertRedirect();
    $this->get($this->tenantUrl($this->tenant, '/account/support/1'))->assertRedirect();
});
