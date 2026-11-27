<?php

namespace App\Modules\Support\Listeners;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Events\CustomerTicketOpened;
use App\Modules\Support\Events\CustomerTicketReplied;
use App\Modules\Support\Events\CustomerTicketStatusChanged;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Notifications\CustomerTicketNotification;
use App\Modules\Support\Notifications\StoreSupportNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Customer ticket notifications (both sides, store database):
 *  - customer opened / replied / closed → store staff with support.view (vendor bell +
 *    inbox + Reverb push, category "support");
 *  - store replied / resolved / closed / reopened → the customer (inbox + e-mail per the
 *    "Support" switch; mobile push through QueueCustomerPush).
 */
class SendCustomerTicketNotifications
{
    public function __construct(private readonly VendorStaffRepositoryContract $staff) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(CustomerTicketOpened::class, [self::class, 'opened']);
        $events->listen(CustomerTicketReplied::class, [self::class, 'replied']);
        $events->listen(CustomerTicketStatusChanged::class, [self::class, 'statusChanged']);
    }

    public function opened(CustomerTicketOpened $event): void
    {
        $this->notifyStaff($event->ticket, 'customer_opened', ['excerpt' => self::excerpt($event->message->body)]);
    }

    public function replied(CustomerTicketReplied $event): void
    {
        $excerpt = self::excerpt($event->message->body);

        if ($event->message->author_type === TicketAuthor::Customer) {
            $this->notifyStaff($event->ticket, 'customer_replied', ['excerpt' => $excerpt]);

            return;
        }

        $this->notifyCustomer($event->ticket, 'ticket_replied', ['excerpt' => $excerpt, 'author' => $event->message->author_name]);
    }

    public function statusChanged(CustomerTicketStatusChanged $event): void
    {
        if ($event->by === TicketAuthor::Customer) {
            $this->notifyStaff($event->ticket, 'customer_closed', []);

            return;
        }

        $name = match ($event->to) {
            TicketStatus::Resolved => 'ticket_resolved',
            TicketStatus::Closed => 'ticket_closed',
            default => 'ticket_reopened',
        };

        $this->notifyCustomer($event->ticket, $name, []);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function notifyStaff(CustomerTicket $ticket, string $event, array $extra): void
    {
        $recipients = $this->staff->activeWithPermission('support.view');

        if ($recipients->isEmpty()) {
            return;
        }

        $ticket->loadMissing('customer');

        Notification::send($recipients, new StoreSupportNotification(
            $event,
            $this->params($ticket) + ['author' => (string) ($ticket->customer->name ?? '')] + $extra,
            'support.customers.show',
            ['ticket' => $ticket->id],
        ));
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function notifyCustomer(CustomerTicket $ticket, string $event, array $extra): void
    {
        $customer = Customer::query()->find($ticket->customer_id);

        if (! $customer instanceof Customer) {
            return;
        }

        $customer->notify(new CustomerTicketNotification($event, (int) $ticket->id, $this->params($ticket) + $extra, $this->ticketUrl($ticket)));
    }

    /**
     * @return array<string, string>
     */
    private function params(CustomerTicket $ticket): array
    {
        $tenant = tenant();

        return [
            'reference' => $ticket->displayReference(),
            'subject' => $ticket->subject,
            'store' => $tenant instanceof Tenant ? (string) $tenant->name : '',
        ];
    }

    private function ticketUrl(CustomerTicket $ticket): ?string
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! Route::has('storefront.support.show')) {
            return null;
        }

        return StoreSettingsService::storeUrl($tenant).route('storefront.support.show', ['ticket' => $ticket->id], false);
    }

    private static function excerpt(?string $body): string
    {
        return Str::limit(trim((string) $body), 140);
    }
}
