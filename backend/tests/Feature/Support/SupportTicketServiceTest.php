<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\DTOs\TicketUpdateData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Events\TicketOpened;
use App\Modules\Support\Events\TicketReplied;
use App\Modules\Support\Events\TicketStatusChanged;
use App\Modules\Support\Events\TicketThreadUpdated;
use App\Modules\Support\Exceptions\SupportRuleException;
use App\Modules\Support\Notifications\SupportTicketNotification;
use App\Modules\Support\Services\SupportTicketService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SupportHelpers.php';

/*
| Support ticket rules: SLA per priority, status transitions on replies, internal notes
| (no status change, no event), close / reopen guards, domain events, and the MAIL-037
| reply e-mail (admin template when active, built-in branded layout otherwise).
*/

beforeEach(function (): void {
    Storage::fake('central');
    $this->service = app(SupportTicketService::class);
    $this->admin = new TicketActor(TicketAuthor::Admin, 7, 'Laila');
    $this->vendor = new TicketActor(TicketAuthor::Vendor, 3, 'Mona');
});

it('sets the SLA target from the priority and labels it left / overdue', function (): void {
    $this->travelTo(now()->startOfMinute());
    $ticket = supportTicket(null, priority: TicketPriority::Urgent);

    expect((int) round($ticket->created_at->diffInHours($ticket->sla_due_at)))->toBe(4)
        ->and($this->service->sla($ticket)['label'])->toBe(__('support::tickets.sla.left', ['time' => __('support::tickets.sla.hours', ['hours' => 4, 'minutes' => 0])]))
        ->and($this->service->sla($ticket, now()->addMinutes(230))['tone'])->toBe('warning')
        ->and($this->service->sla($ticket, now()->addHours(5))['tone'])->toBe('danger')
        ->and($this->service->sla($ticket, now()->addHours(5))['label'])->toContain('1 h 0 m');

    $this->service->update($ticket, new TicketUpdateData(priority: TicketPriority::Low), $this->admin);
    expect((int) round($ticket->fresh()->last_reply_at->diffInHours($ticket->fresh()->sla_due_at)))->toBe(48);

    $this->service->reply($ticket->fresh(), new TicketReplyData($this->admin, 'On it.'));
    expect($this->service->sla($ticket->fresh()))->toBeNull();
});

it('moves the status on replies and keeps internal notes silent', function (): void {
    Event::fake([TicketReplied::class, TicketStatusChanged::class, TicketThreadUpdated::class]);
    $ticket = supportTicket(null);

    $this->service->reply($ticket, new TicketReplyData($this->admin, 'Internal check', internal: true));
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    Event::assertNotDispatched(TicketReplied::class);

    $this->service->reply($ticket->fresh(), new TicketReplyData($this->admin, 'Please try again.'));
    expect($ticket->fresh()->status)->toBe(TicketStatus::Answered);

    $this->service->update($ticket->fresh(), new TicketUpdateData(status: TicketStatus::Resolved), $this->admin);
    $this->service->reply($ticket->fresh(), new TicketReplyData($this->vendor, 'It broke again.'));
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open)->and($ticket->fresh()->resolved_at)->toBeNull();

    Event::assertDispatched(TicketReplied::class, 2);
    Event::assertDispatched(TicketStatusChanged::class, fn (TicketStatusChanged $event): bool => $event->to === TicketStatus::Resolved && $event->by === TicketAuthor::Admin);
    Event::assertDispatched(TicketThreadUpdated::class, fn (TicketThreadUpdated $event): bool => $event->broadcastOn()->name === 'private-support.tickets.'.$ticket->id);
});

it('guards empty messages, closed tickets and repeated close / reopen', function (): void {
    $ticket = supportTicket(null);

    expect(fn () => $this->service->reply($ticket, new TicketReplyData($this->vendor, '   ')))->toThrow(SupportRuleException::class);

    $this->service->close($ticket, $this->vendor);
    expect(fn () => $this->service->reply($ticket->fresh(), new TicketReplyData($this->vendor, 'Hello')))->toThrow(SupportRuleException::class)
        ->and(fn () => $this->service->close($ticket->fresh(), $this->vendor))->toThrow(SupportRuleException::class);

    // An admin may still answer a closed ticket: it comes back as answered.
    $this->service->reply($ticket->fresh(), new TicketReplyData($this->admin, 'Final note for you.'));
    expect($ticket->fresh()->status)->toBe(TicketStatus::Answered)->and($ticket->fresh()->closed_at)->toBeNull();

    expect(fn () => $this->service->reopen($ticket->fresh(), $this->vendor))->toThrow(SupportRuleException::class)
        ->and(fn () => $this->service->update($ticket->fresh(), new TicketUpdateData, $this->admin))->toThrow(SupportRuleException::class);
});

it('fires TicketOpened with the first message', function (): void {
    Event::fake([TicketOpened::class]);
    $ticket = supportTicket(null, 'SSL pending');

    Event::assertDispatched(TicketOpened::class, fn (TicketOpened $event): bool => $event->ticket->is($ticket) && str_contains((string) $event->message->body, 'Tabby'));
});

it('renders the MAIL-037 reply e-mail with the built-in layout in both languages', function (): void {
    $owner = Owner::factory()->create(['name' => 'Mohamed']);
    $notification = new SupportTicketNotification('admin_replied', ['reference' => 'T-4471', 'subject' => 'Shipping zone', 'store' => 'Megz', 'author' => 'Laila', 'excerpt' => 'We fixed the shipping zone sync.'], 'https://example.test/support/4471');

    $html = (string) $notification->toMail($owner)->render();
    expect($html)->toContain(__('support::tickets.mail.heading', ['reference' => 'T-4471']))
        ->toContain('We fixed the shipping zone sync.')
        ->toContain('Hi Mohamed,')
        ->toContain('https://example.test/support/4471')
        ->toContain(__('support::tickets.mail.cta'));

    app()->setLocale('ar');
    $arabic = (string) $notification->toMail($owner)->render();
    expect($arabic)->toContain('dir="rtl"')->toContain('الرد على التذكرة');
    app()->setLocale('en');
});

it('uses the admin template of support.ticket_reply when it is active', function (): void {
    $owner = Owner::factory()->create(['name' => 'Mohamed']);
    EmailTemplate::factory()->event(EmailTemplateEvent::SupportTicketReply)->create([
        'subject' => ['en' => 'Reply on {{ ticket.reference }}', 'ar' => 'رد على {{ ticket.reference }}'],
        'body' => ['en' => '<p>Hi {{ recipient.name }}, {{ reply.excerpt }}</p>', 'ar' => '<p>مرحباً {{ recipient.name }}، {{ reply.excerpt }}</p>'],
    ]);

    $mail = (new SupportTicketNotification('admin_replied', ['reference' => 'T-4471', 'subject' => 'Zone', 'store' => 'Megz', 'author' => 'Laila', 'excerpt' => 'Try again now.'], 'https://example.test/t'))->toMail($owner);

    expect($mail->subject)->toBe('Reply on T-4471')
        ->and((string) $mail->render())->toContain('Hi Mohamed, Try again now.');
});

it('keeps non-reply notifications on the generic mail and drops mail when asked', function (): void {
    $owner = Owner::factory()->create();
    $status = new SupportTicketNotification('status_changed', ['reference' => 'T-1', 'subject' => 'S', 'status' => 'Resolved', 'store' => 'M'], null, false);

    expect($status->via($owner))->not->toContain('mail')
        ->and($status->toArray($owner)['category'])->toBe('support');
});
