<?php

namespace App\Modules\Support\Listeners;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Events\TicketAssigned;
use App\Modules\Support\Events\TicketOpened;
use App\Modules\Support\Events\TicketReplied;
use App\Modules\Support\Events\TicketStatusChanged;
use App\Modules\Support\Jobs\NotifySupportStaff;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Notifications\SupportTicketNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * Support events → shared Notifications module:
 *  - new ticket / requester reply → the assigned admin, else every admin with
 *    support.tickets.manage (database + mail + Reverb; replies use MAIL-037);
 *  - admin reply / status change → the requester side: the store's staff with
 *    support.view (bell + live push via NotifySupportStaff; the opener also gets the reply
 *    e-mail) and the owner account (inbox; e-mail when the owner opened the ticket);
 *  - requester close / reopen → admins (inbox only); assignment → the new assignee.
 */
class SendSupportNotifications
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly OwnerRepositoryContract $owners,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TicketOpened::class, [self::class, 'opened']);
        $events->listen(TicketReplied::class, [self::class, 'replied']);
        $events->listen(TicketStatusChanged::class, [self::class, 'statusChanged']);
        $events->listen(TicketAssigned::class, [self::class, 'assigned']);
    }

    public function opened(TicketOpened $event): void
    {
        $params = $this->params($event->ticket) + [
            'author' => $event->ticket->opened_by_name,
            'excerpt' => Str::limit((string) $event->message->body, 160),
            'priority' => $event->ticket->priority->label(),
        ];

        $this->notifyAdmins($event->ticket, 'opened', $params, true);
    }

    public function replied(TicketReplied $event): void
    {
        $params = $this->params($event->ticket) + [
            'author' => $event->message->author_name,
            'excerpt' => Str::limit((string) $event->message->body, 300),
        ];

        if ($event->message->author_type->isRequester()) {
            $this->notifyAdmins($event->ticket, 'requester_replied', $params, true);

            return;
        }

        $this->notifyRequester($event->ticket, 'admin_replied', $params, true);
    }

    public function statusChanged(TicketStatusChanged $event): void
    {
        $params = $this->params($event->ticket) + ['status' => $event->to->label(), 'author' => $event->byName];

        if ($event->by->isRequester()) {
            $this->notifyAdmins($event->ticket, $event->to === TicketStatus::Closed ? 'requester_closed' : 'requester_reopened', $params, false);

            return;
        }

        $params['status'] = $event->to->requesterLabel();
        $this->notifyRequester($event->ticket, 'status_changed', $params, false);
    }

    public function assigned(TicketAssigned $event): void
    {
        $this->central(function () use ($event): void {
            $admin = $this->admins->find($event->adminId);

            if ($admin instanceof Admin && $admin->can('support.tickets.view')) {
                $this->dispatcher->send($admin, new SupportTicketNotification(
                    'assigned',
                    $this->params($event->ticket) + ['author' => $event->byName],
                    route('admin.support.tickets.show', $event->ticket->id),
                    false,
                ));
            }
        });
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function notifyAdmins(SupportTicket $ticket, string $event, array $params, bool $withMail): void
    {
        // Vendor events fire inside the store's tenant context; admins live centrally.
        $this->central(function () use ($ticket, $event, $params, $withMail): void {
            $assignee = $ticket->assigned_admin_id !== null ? $this->admins->find($ticket->assigned_admin_id) : null;
            $recipients = $assignee instanceof Admin && $assignee->can('support.tickets.view')
                ? collect([$assignee])
                : $this->admins->activeWithPermission('support.tickets.manage');

            if ($recipients->isEmpty()) {
                return;
            }

            $this->dispatcher->send($recipients, new SupportTicketNotification($event, $params, route('admin.support.tickets.show', $ticket->id), $withMail));
        });
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function notifyRequester(SupportTicket $ticket, string $event, array $params, bool $withMail): void
    {
        $openedByOwner = $ticket->opened_by_type === TicketAuthor::Owner;

        if ($ticket->tenant_id !== null) {
            NotifySupportStaff::dispatch(
                (int) $ticket->tenant_id,
                (int) $ticket->id,
                $event,
                $params,
                $withMail && ! $openedByOwner ? $ticket->opened_by_id : null,
            );
        }

        $ownerId = $ticket->owner_id;

        if ($ownerId === null && $ticket->tenant_id !== null) {
            $tenant = $this->tenants->find($ticket->tenant_id);
            $ownerId = $tenant instanceof Tenant ? $tenant->owner_id : null;
        }

        $owner = $ownerId !== null ? $this->owners->find($ownerId) : null;

        if ($owner instanceof Owner) {
            $this->dispatcher->send($owner, new SupportTicketNotification($event, $params, route('owner.support.tickets.show', $ticket->id), $withMail && $openedByOwner));
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function params(SupportTicket $ticket): array
    {
        $tenant = $ticket->tenant_id !== null ? $this->tenants->find($ticket->tenant_id) : null;

        return [
            'reference' => $ticket->displayReference(),
            'subject' => $ticket->subject,
            'store' => $tenant instanceof Tenant ? (string) $tenant->name : (string) __('support::tickets.account_ticket'),
        ];
    }

    private function central(callable $callback): void
    {
        tenancy()->initialized ? tenancy()->central($callback) : $callback();
    }
}
