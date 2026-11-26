<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Notifications\SupportTicketNotification;
use App\Modules\Support\Services\SupportTicketService;
use App\Modules\Support\Support\SupportBadgeCounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SupportHelpers.php';
require_once __DIR__.'/../Owner/OwnerHelpers.php';

/*
| ADM-116 / 116a Admin › Support tickets: permissions, tabs + server-side list with
| filters, detail with the conversation, replies + internal notes with attachments,
| status / priority / assignee, resolve, polling and the sidebar badge.
*/

beforeEach(function (): void {
    Storage::fake('central');
    Notification::fake();
    $this->owner = Owner::factory()->create(['name' => 'Mohamed Owner']);
    $this->store = ownerStore($this->owner, ['name' => 'Megz Store', 'slug' => 'megz-store']);
    $this->ticket = supportTicket($this->store);
});

it('redirects guests and forbids admins without the support permissions', function (): void {
    $this->get(route('admin.support.tickets.index'))->assertRedirect();

    $admin = supportAdmin([]);
    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.index'))->assertForbidden();
    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.show', $this->ticket->id))->assertForbidden();

    $viewer = supportAdmin(['support.tickets.view']);
    $this->actingAs($viewer, 'admin')->postJson(route('admin.support.tickets.reply', $this->ticket->id), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($viewer, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['status' => 'resolved'])->assertForbidden();
    $this->actingAs($viewer, 'admin')->postJson(route('admin.support.tickets.resolve', $this->ticket->id))->assertForbidden();
});

it('lists tickets per tab with filters, search and the sidebar badge', function (): void {
    $urgent = supportTicket($this->store, 'Custom domain SSL pending', priority: TicketPriority::Urgent);
    $resolved = supportTicket(null, 'How to add French?', new TicketActor(TicketAuthor::Owner, $this->owner->id, 'Mohamed Owner'), $this->owner->id);
    $resolved->update(['status' => TicketStatus::Resolved]);
    $admin = supportAdmin(['support.tickets.view']);

    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.index'))
        ->assertOk()
        ->assertSee(__('support::tickets.title'))
        ->assertSee(__('support::tickets.tabs.waiting'))
        ->assertSee(route('admin.support.tickets.data'), false)
        ->assertSee('data-page="admin-support"', false)
        ->assertSee(route('admin.support.tickets.index'), false);

    $open = supportRows(route('admin.support.tickets.data', ['tab' => 'open']));
    expect($open)->toHaveCount(2)
        ->and(implode('', array_column($open, 'ticket')))->toContain('Custom domain SSL pending')->toContain($this->ticket->displayReference());

    expect(supportRows(route('admin.support.tickets.data', ['tab' => 'resolved'])))->toHaveCount(1)
        ->and(supportRows(route('admin.support.tickets.data', ['tab' => 'open', 'priority' => 'urgent'])))->toHaveCount(1)
        ->and(supportRows(route('admin.support.tickets.data', ['tab' => 'all', 'search' => 'French'])))->toHaveCount(1)
        ->and(supportRows(route('admin.support.tickets.data', ['tab' => 'all', 'tenant_id' => $this->store->id])))->toHaveCount(2)
        ->and(supportRows(route('admin.support.tickets.data', ['tab' => 'all', 'assignee' => 'none'])))->toHaveCount(3);

    $rows = supportRows(route('admin.support.tickets.data', ['tab' => 'all', 'search' => 'French']));
    expect($rows[0]['store'])->toContain(__('support::tickets.account_ticket'));

    $this->actingAs($admin, 'admin')->getJson(route('admin.support.tickets.stores', ['q' => 'Megz']))
        ->assertOk()->assertJsonPath('results.0.id', $this->store->id);

    // Sidebar badge = open tickets.
    expect(app(SupportBadgeCounter::class)->counts($admin))->toBe(['support' => 2]);
});

it('opens a ticket, marks it read, shows SLA, files and the impersonate link', function (): void {
    $admin = supportAdmin();
    expect($this->ticket->fresh()->admin_unread)->toBeTrue();

    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.show', $this->ticket->id))
        ->assertOk()
        ->assertSee($this->ticket->title())
        ->assertSee('Customers in KSA do not see Tabby')
        ->assertSee('Megz Store')
        ->assertSee(__('support::tickets.actions.impersonate'))
        ->assertSee(route('admin.vendors.show', ['tenant' => $this->store->id, 'open' => 'impersonate']), false)
        ->assertSee(__('support::tickets.thread.internal'))
        ->assertSee('m left');

    expect($this->ticket->fresh()->admin_unread)->toBeFalse();
    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.show', 999999))->assertNotFound();
});

it('replies with files, saves internal notes and notifies the requester side only for replies', function (): void {
    $admin = supportAdmin(null, ['name' => 'Sara Admin']);

    $this->actingAs($admin, 'admin')->postJson(route('admin.support.tickets.reply', $this->ticket->id), [])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->actingAs($admin, 'admin')->post(route('admin.support.tickets.reply', $this->ticket->id), ['files' => [UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');

    $this->actingAs($admin, 'admin')->post(route('admin.support.tickets.reply', $this->ticket->id), [
        'body' => 'Your Tabby merchant ID was rotated.',
        'files' => [UploadedFile::fake()->image('gateway.png')],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('success', true);

    $ticket = $this->ticket->fresh();
    expect($ticket->status)->toBe(TicketStatus::Answered)
        ->and($ticket->requester_unread)->toBeTrue()
        ->and($ticket->sla_due_at)->toBeNull();

    $reply = SupportTicketMessage::query()->where('author_type', TicketAuthor::Admin->value)->sole();
    expect($reply->attachments)->toHaveCount(1);
    Storage::disk('central')->assertExists($reply->attachments->first()->path);

    Notification::assertSentTo($this->owner, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'admin_replied' && $n->withMail === false);

    $this->actingAs($admin, 'admin')->postJson(route('admin.support.tickets.reply', $this->ticket->id), ['body' => 'Checked logs, merchant ID changed on their side.', 'internal' => 1])
        ->assertCreated()->assertJsonPath('message', __('support::tickets.note_saved'));

    $note = SupportTicketMessage::query()->where('is_internal', true)->sole();
    expect($note->body)->toContain('merchant ID changed')
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Answered);
    Notification::assertSentToTimes($this->owner, SupportTicketNotification::class, 1);

    // Polling returns the admin's view (internal notes included).
    $json = $this->actingAs($admin, 'admin')->getJson(route('admin.support.tickets.messages', ['ticket' => $this->ticket->id, 'after' => 0]))->assertOk()->json('data');
    expect($json['html'])->toContain('merchant ID changed')->toContain(__('support::tickets.thread.internal_badge'))
        ->and($json['status']['label'])->toBe(TicketStatus::Answered->label());

    // Admin downloads the note-less attachment.
    $this->actingAs($admin, 'admin')->get(route('admin.support.tickets.attachments.show', ['attachment' => $reply->attachments->first()->id, 'download' => 1]))->assertOk();
});

it('changes status, priority and assignee, notifies the assignee and resolves', function (): void {
    $admin = supportAdmin(null, ['name' => 'Sara Admin']);
    $agent = supportAdmin(['support.tickets.view', 'support.tickets.manage'], ['name' => 'Omar Haddad']);
    $outsider = supportAdmin(['orders.view']);

    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['status' => 'nope', 'assigned_admin_id' => $outsider->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['status', 'assigned_admin_id']);
    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['priority' => 'normal'])
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['priority' => 'urgent'])->assertOk();
    expect($this->ticket->fresh()->priority)->toBe(TicketPriority::Urgent);

    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['assigned_admin_id' => $agent->id])->assertOk();
    expect($this->ticket->fresh()->assigned_admin_id)->toBe($agent->id);
    Notification::assertSentTo($agent, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'assigned');

    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['status' => 'awaiting_reply'])->assertOk()
        ->assertJsonPath('data.status.value', 'awaiting_reply');
    Notification::assertSentTo($this->owner, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'status_changed');

    $this->actingAs($admin, 'admin')->postJson(route('admin.support.tickets.resolve', $this->ticket->id))->assertOk()
        ->assertJsonPath('redirect', route('admin.support.tickets.show', $this->ticket->id));
    expect($this->ticket->fresh()->status)->toBe(TicketStatus::Resolved)->and($this->ticket->fresh()->resolved_at)->not->toBeNull();

    $this->actingAs($admin, 'admin')->patchJson(route('admin.support.tickets.update', $this->ticket->id), ['assigned_admin_id' => null])->assertOk();
    expect($this->ticket->fresh()->assigned_admin_id)->toBeNull();
});

it('sends new tickets and requester replies to the assignee, else to every support manager', function (): void {
    $manager = supportAdmin(['support.tickets.view', 'support.tickets.manage']);
    $viewer = supportAdmin(['support.tickets.view']);
    $ticket = supportTicket($this->store, 'Invoice copy');

    Notification::assertSentTo($manager, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'opened' && $n->withMail);
    Notification::assertNotSentTo($viewer, SupportTicketNotification::class);

    $ticket->update(['assigned_admin_id' => $viewer->id]);
    app(SupportTicketService::class)->reply($ticket->fresh(), new TicketReplyData(
        new TicketActor(TicketAuthor::Vendor, 1, 'Mona'), 'Any news?',
    ));

    Notification::assertSentTo($viewer, SupportTicketNotification::class, fn (SupportTicketNotification $n): bool => $n->eventName === 'requester_replied');
    expect(SupportTicket::query()->find($ticket->id)->status)->toBe(TicketStatus::Open);
});
