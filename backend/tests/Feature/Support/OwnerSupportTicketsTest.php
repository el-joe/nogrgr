<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Notifications\SupportTicketNotification;
use App\Modules\Support\Services\SupportTicketService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SupportHelpers.php';
require_once __DIR__.'/../Owner/OwnerHelpers.php';

/*
| OWN-009 Owner portal › Help & support tickets: list (own + stores'), new ticket for a
| store or the account, other owners' tickets 404, reply, close / reopen, attachments,
| and the reply e-mail (MAIL-037) when the owner opened the ticket.
*/

beforeEach(function (): void {
    Storage::fake('central');
    Notification::fake();
    $this->owner = Owner::factory()->create(['name' => 'Mohamed Owner']);
    $this->store = ownerStore($this->owner, ['name' => 'Desert Rose']);
    $this->stranger = Owner::factory()->create();
    $this->strangerStore = ownerStore($this->stranger, ['name' => 'Lina Boutique']);
});

it('protects every ticket route', function (): void {
    $this->get(route('owner.support.tickets.create'))->assertRedirect();
    $this->getJson(route('owner.support.tickets.data'))->assertUnauthorized();
});

it('shows the tickets section on the help page and lists own and store tickets only', function (): void {
    supportTicket($this->store, 'Store ticket from staff');
    supportTicket(null, 'Account billing question', TicketActor::owner($this->owner), $this->owner->id);
    supportTicket($this->strangerStore, 'Someone else');

    $this->actingAs($this->owner, 'owner')->get(route('owner.support'))
        ->assertOk()
        ->assertSee(__('support::tickets.owner_title'))
        ->assertSee(route('owner.support.tickets.data'), false)
        ->assertSee('data-page="owner-support"', false);

    $rows = $this->actingAs($this->owner, 'owner')->getJson(route('owner.support.tickets.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->json('data');
    $html = implode('', array_column($rows, 'ticket'));
    expect($rows)->toHaveCount(2)->and($html)->toContain('Store ticket from staff')->toContain('Account billing question')->not->toContain('Someone else');
});

it('opens a ticket for a store or the account and validates the store', function (): void {
    $admin = supportAdmin(['support.tickets.view', 'support.tickets.manage']);

    $this->actingAs($this->owner, 'owner')->getJson(route('owner.support.tickets.create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Desert Rose');
    $this->actingAs($this->owner, 'owner')->get(route('owner.support.tickets.create'))->assertOk()->assertSee(__('support::tickets.new_ticket_title'));

    $payload = ['subject' => 'Renewal failed', 'category' => 'billing', 'priority' => 'high', 'body' => 'My card was charged twice this month.'];

    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.store'), [...$payload, 'tenant_id' => $this->strangerStore->id])
        ->assertUnprocessable()->assertJsonValidationErrors('tenant_id');

    $this->actingAs($this->owner, 'owner')->post(route('owner.support.tickets.store'), [...$payload, 'tenant_id' => $this->store->id, 'files' => [UploadedFile::fake()->create('statement.pdf', 50, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertCreated();
    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.store'), $payload)->assertCreated();

    $tickets = SupportTicket::query()->orderBy('id')->get();
    expect($tickets)->toHaveCount(2)
        ->and($tickets[0]->tenant_id)->toBe($this->store->id)
        ->and($tickets[0]->owner_id)->toBe($this->owner->id)
        ->and($tickets[0]->opened_by_type)->toBe(TicketAuthor::Owner)
        ->and($tickets[1]->tenant_id)->toBeNull();

    Notification::assertSentToTimes($admin, SupportTicketNotification::class, 2);
});

it('reads, replies, closes and reopens its ticket and cannot reach other owners\' tickets', function (): void {
    $ticket = supportTicket($this->store, 'Payout delayed', TicketActor::owner($this->owner), $this->owner->id);
    $foreign = supportTicket($this->strangerStore, 'Not yours');
    $reply = app(SupportTicketService::class)->reply($ticket, new TicketReplyData(new TicketActor(TicketAuthor::Admin, 1, 'Laila'), 'Payout is scheduled for Monday.', [UploadedFile::fake()->image('schedule.png')]));
    app(SupportTicketService::class)->reply($ticket->fresh(), new TicketReplyData(new TicketActor(TicketAuthor::Admin, 1, 'Laila'), 'HIDDEN internal note', internal: true));

    Notification::assertSentTo($this->owner, SupportTicketNotification::class, fn (SupportTicketNotification $n, array $channels): bool => $n->eventName === 'admin_replied' && $n->withMail);

    $this->actingAs($this->owner, 'owner')->get(route('owner.support.tickets.show', $ticket->id))
        ->assertOk()
        ->assertSee('Payout is scheduled for Monday.')
        ->assertDontSee('HIDDEN internal note')
        ->assertSee('schedule.png');
    expect($ticket->fresh()->requester_unread)->toBeFalse();

    $this->actingAs($this->owner, 'owner')->get(route('owner.support.tickets.attachments.show', $reply->attachments->first()->id))->assertOk();
    $this->actingAs($this->owner, 'owner')->get(route('owner.support.tickets.show', $foreign->id))->assertNotFound();
    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.reply', $foreign->id), ['body' => 'x'])->assertNotFound();

    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.reply', $ticket->id), ['body' => 'Thanks, noted.'])->assertCreated();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);

    $json = $this->actingAs($this->owner, 'owner')->getJson(route('owner.support.tickets.messages', ['ticket' => $ticket->id, 'after' => 0]))->assertOk()->json('data');
    expect($json['html'])->toContain('Thanks, noted.')->not->toContain('HIDDEN');

    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.close', $ticket->id))->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
    $this->actingAs($this->owner, 'owner')->postJson(route('owner.support.tickets.reopen', $ticket->id))->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});
