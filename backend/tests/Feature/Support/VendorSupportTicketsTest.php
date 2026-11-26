<?php

use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Jobs\NotifySupportStaff;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Notifications\StoreSupportNotification;
use App\Modules\Support\Notifications\SupportTicketNotification;
use App\Modules\Support\Services\SupportTicketService;
use App\Modules\Support\Support\VendorSupportBadgeCounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SupportHelpers.php';

/*
| VEN-091 / 091a / 091b Vendor › Support: permissions, other-store isolation (404),
| new ticket with attachments (validation, admin notification), list + open thread,
| replies, internal notes hidden, close / reopen, attachments, both host modes, badge
| and the staff notification + reply e-mail of an admin answer.
*/

beforeEach(function (): void {
    Storage::fake('central');
    Notification::fake();
    $this->tenant = $this->createTenant('supacme');
    $this->staff = $this->createVendorOwner($this->tenant, ['name' => 'Mona Khaled', 'email' => 'mona@acme.test']);
    $this->other = $this->createTenant('supbeta');
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/support'.$path);
    $this->as = fn () => $this->actingAsVendor($this->staff, $this->tenant);
});

it('sends guests to the store login and forbids staff without support permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $nobody = supportStaff($this->tenant, []);
    $this->actingAsVendor($nobody, $this->tenant)->get(($this->url)())->assertForbidden();
    $this->actingAsVendor($nobody, $this->tenant)->getJson(($this->url)('/data'))->assertForbidden();

    $viewer = supportStaff($this->tenant, ['support.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)())->assertOk()->assertDontSee('support-ticket-modal', false);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)('/create'))->assertForbidden();
    $this->actingAsVendor($viewer, $this->tenant)->postJson(($this->url)(), ['subject' => 'Help'])->assertForbidden();
});

it('shows the empty state, validates and opens a ticket with attachments', function (): void {
    $admin = supportAdmin(['support.tickets.view', 'support.tickets.manage']);

    ($this->as)()->get(($this->url)())
        ->assertOk()
        ->assertSee(__('support::tickets.empty.vendor_title'))
        ->assertSee('data-page="vendor-support"', false)
        ->assertSee(__('support::tickets.new_ticket'));

    ($this->as)()->getJson(($this->url)('/create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('support-ticket-form', false);
    ($this->as)()->get(($this->url)('/create'))->assertOk()->assertSee(__('support::tickets.new_ticket_title'));

    ($this->as)()->postJson(($this->url)(), ['subject' => 'x', 'category' => 'bogus', 'priority' => 'meh'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'category', 'priority', 'body']);

    ($this->as)()->post(($this->url)(), [
        'subject' => 'Tabby not showing at checkout',
        'category' => 'payments',
        'priority' => 'urgent',
        'body' => 'Customers in KSA do not see Tabby since this morning.',
        'files' => [UploadedFile::fake()->create('checkout-screenshot.pdf', 120, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('success', true);

    $ticket = SupportTicket::withoutTenantScope()->sole();
    expect($ticket->tenant_id)->toBe($this->tenant->id)
        ->and($ticket->opened_by_type)->toBe(TicketAuthor::Vendor)
        ->and($ticket->opened_by_name)->toBe('Mona Khaled')
        ->and($ticket->reference)->toBe('T-'.(1000 + $ticket->id))
        ->and($ticket->sla_due_at->diffInHours($ticket->created_at, true))->toBeGreaterThanOrEqual(3.9);

    $message = SupportTicketMessage::withoutTenantScope()->sole();
    expect($message->attachments)->toHaveCount(1)->and($message->tenant_id)->toBe($this->tenant->id);

    Notification::assertSentTo($admin, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'opened');
});

it('lists only the store\'s tickets, opens the thread and hides internal notes', function (): void {
    $own = supportTicket($this->tenant, 'Change store URL');
    $foreign = supportTicket($this->other, 'Foreign invoice copy');
    $service = app(SupportTicketService::class);
    $admin = new TicketActor(TicketAuthor::Admin, 1, 'Sara');
    $service->reply($own, new TicketReplyData($admin, 'Please re-enter the merchant ID.'));
    $service->reply($own->fresh(), new TicketReplyData($admin, 'SECRET internal remark', internal: true));

    expect(app(VendorSupportBadgeCounter::class)->counts(null))->toBe([]);

    $rows = ($this->as)()->getJson(($this->url)('/data?draw=1&start=0&length=10'))->assertOk()->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['ticket'])->toContain('Change store URL')->not->toContain('Foreign');

    ($this->as)()->get(($this->url)())
        ->assertOk()
        ->assertSee('Change store URL')
        ->assertSee('Please re-enter the merchant ID.')
        ->assertDontSee('SECRET internal remark')
        ->assertSee(__('support::enums.ticket_status_requester.answered'));

    expect($own->fresh()->requester_unread)->toBeFalse();

    ($this->as)()->get(($this->url)('/'.$foreign->id))->assertNotFound();
    ($this->as)()->getJson(($this->url)('/'.$foreign->id.'/messages'))->assertNotFound();
    ($this->as)()->postJson(($this->url)('/'.$foreign->id.'/messages'), ['body' => 'hi'])->assertNotFound();

    $json = ($this->as)()->getJson(($this->url)('/'.$own->id.'/messages?after=0'))->assertOk()->json('data');
    expect($json['html'])->toContain('merchant ID')->not->toContain('SECRET');
});

it('replies, closes, refuses replies when closed and reopens', function (): void {
    $admin = supportAdmin(['support.tickets.view', 'support.tickets.manage']);
    $ticket = supportTicket($this->tenant);
    $ticket->update(['status' => TicketStatus::Answered]);

    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/messages'), [])->assertUnprocessable()->assertJsonValidationErrors('body');
    ($this->as)()->post(($this->url)('/'.$ticket->id.'/messages'), ['body' => 'Still failing.', 'files' => [UploadedFile::fake()->image('error.png')]], ['Accept' => 'application/json'])->assertCreated();

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe(TicketStatus::Open)->and($fresh->admin_unread)->toBeTrue()->and($fresh->sla_due_at)->not->toBeNull();
    Notification::assertSentTo($admin, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'requester_replied');

    $file = SupportTicketMessage::withoutTenantScope()->where('body', 'Still failing.')->sole()->attachments->first();
    ($this->as)()->get($this->tenantUrl($this->tenant, 'admin/support/attachments/'.$file->id))->assertOk();
    $foreignTicket = supportTicket($this->other);
    $foreignFile = app(SupportTicketService::class)->reply($foreignTicket, new TicketReplyData(new TicketActor(TicketAuthor::Vendor, 9, 'Other'), null, [UploadedFile::fake()->image('x.png')]))->attachments->first();
    ($this->as)()->get($this->tenantUrl($this->tenant, 'admin/support/attachments/'.$foreignFile->id))->assertNotFound();

    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/close'))->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
    Notification::assertSentTo($admin, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'requester_closed');

    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/close'))->assertUnprocessable();
    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/messages'), ['body' => 'Hello?'])->assertUnprocessable()->assertJsonPath('message', __('support::tickets.errors.closed'));
    ($this->as)()->get(($this->url)('/'.$ticket->id))->assertOk()->assertSee(__('support::tickets.thread.closed_notice'))->assertSee(__('support::tickets.actions.reopen'));

    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/reopen'))->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    ($this->as)()->postJson(($this->url)('/'.$ticket->id.'/reopen'))->assertUnprocessable();
});

it('works on the central host mode too', function (): void {
    $ticket = supportTicket($this->tenant, 'Central host ticket');

    ($this->as)()->get($this->centralPanelUrl($this->tenant, 'support/'.$ticket->id))
        ->assertOk()
        ->assertSee('Central host ticket');
});

it('notifies the store staff of an admin reply and e-mails the opener only', function (): void {
    $ticket = supportTicket($this->tenant, 'Domain SSL', new TicketActor(TicketAuthor::Vendor, $this->staff->id, 'Mona Khaled', 'mona@acme.test'));
    $colleague = supportStaff($this->tenant, ['support.view']);

    (new NotifySupportStaff($this->tenant->id, $ticket->id, 'admin_replied', ['reference' => $ticket->displayReference(), 'subject' => 'Domain SSL', 'store' => 'Supacme', 'author' => 'Sara', 'excerpt' => 'Fixed.'], $this->staff->id))
        ->handle(app(TenantRepositoryContract::class));

    $this->tenant->run(function () use ($colleague): void {
        Notification::assertSentTo($this->staff, StoreSupportNotification::class, fn (StoreSupportNotification $n, array $channels): bool => in_array('mail', $channels, true) && $n->vendorRoute === 'support.show');
        Notification::assertSentTo($colleague, StoreSupportNotification::class, fn (StoreSupportNotification $n, array $channels): bool => ! in_array('mail', $channels, true));
    });

    $mail = (new StoreSupportNotification('admin_replied', ['reference' => 'T-1001', 'subject' => 'Domain SSL', 'author' => 'Sara', 'excerpt' => 'We fixed the shipping zone sync.', 'store' => 'Supacme'], 'support.show', ['ticket' => $ticket->id], $this->staff->id, 'https://example.test/t'))
        ->toMail($this->staff);
    expect((string) $mail->render())->toContain('We fixed the shipping zone sync.')->toContain(__('support::tickets.mail.cta'))->toContain('https://example.test/t');
});

it('shows the sidebar link with the unread badge', function (): void {
    $ticket = supportTicket($this->tenant);
    app(SupportTicketService::class)->reply($ticket, new TicketReplyData(new TicketActor(TicketAuthor::Admin, 1, 'Sara'), 'Answer'));

    ($this->as)()->get($this->tenantUrl($this->tenant, 'admin/help'))
        ->assertOk()
        ->assertSee(__('navigation.vendor.support'))
        ->assertSee(__('navigation.vendor.help_center'));

    $this->tenant->run(function (): void {
        expect(app(VendorSupportBadgeCounter::class)->counts($this->staff))->toBe(['vendor-support' => 1]);
    });
});
