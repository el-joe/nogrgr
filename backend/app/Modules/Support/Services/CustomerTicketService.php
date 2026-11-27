<?php

namespace App\Modules\Support\Services;

use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Support\DTOs\OpenCustomerTicketData;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Events\CustomerTicketOpened;
use App\Modules\Support\Events\CustomerTicketReplied;
use App\Modules\Support\Events\CustomerTicketStatusChanged;
use App\Modules\Support\Exceptions\SupportRuleException;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketAttachment;
use App\Modules\Support\Models\CustomerTicketMessage;
use App\Modules\Support\Repositories\Contracts\CustomerTicketRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Customer ↔ store support tickets (store database): the customer opens / replies / closes
 * through the Store API (app + theme account), the store staff answer and resolve / close /
 * reopen in Vendor › Support › Customers. Rules mirror the platform tickets (#92):
 * customer message → open (a resolved ticket reopens), store reply → answered (a closed
 * ticket comes back), a closed ticket takes no customer messages. Files: ≤5 × 10 MB on
 * the private central disk under the store's folder. Domain events feed the notifications.
 */
class CustomerTicketService extends BaseService
{
    public const DISK = 'central';

    /**
     * Statuses the store staff can set.
     */
    public const STAFF_STATUSES = [TicketStatus::Resolved, TicketStatus::Closed, TicketStatus::Open];

    public function __construct(
        private readonly CustomerTicketRepositoryContract $tickets,
        private readonly CustomerOrderService $orders,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CustomerTicket>
     */
    public function list(Customer $customer, ?string $group = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tickets->forCustomer((int) $customer->id, in_array($group, ['active', 'closed'], true) ? $group : null, $perPage);
    }

    /**
     * The customer's own ticket or 404.
     */
    public function findForCustomer(Customer $customer, int $id): CustomerTicket
    {
        return $this->tickets->findForCustomer((int) $customer->id, $id) ?? throw new NotFoundHttpException;
    }

    /**
     * Any ticket of the current store (staff) or 404.
     */
    public function find(int $id): CustomerTicket
    {
        $ticket = $this->tickets->find($id);

        return $ticket instanceof CustomerTicket ? $ticket : throw new NotFoundHttpException;
    }

    public function latest(): ?CustomerTicket
    {
        return $this->tickets->latest();
    }

    public function unreadForStore(): int
    {
        return $this->tickets->unreadForStore();
    }

    /**
     * Orders the customer can attach to a new ticket (most recent first).
     *
     * @return list<array{uuid: string, number: string}>
     */
    public function recentOrders(Customer $customer, int $limit = 20): array
    {
        return collect($this->orders->history($customer, [], $limit)->items())
            ->map(static fn (Order $order): array => ['uuid' => (string) $order->uuid, 'number' => (string) $order->number])
            ->values()
            ->all();
    }

    public function open(Customer $customer, OpenCustomerTicketData $data): CustomerTicket
    {
        $body = trim($data->body);

        if ($body === '') {
            throw SupportRuleException::emptyMessage();
        }

        $order = $data->orderUuid !== null ? $this->ownOrder($customer, $data->orderUuid) : null;
        $actor = TicketActor::customer($customer);
        $stored = [];

        try {
            return $this->transaction(function () use ($data, $body, $order, $actor, $customer, &$stored): CustomerTicket {
                /** @var CustomerTicket $ticket */
                $ticket = $this->tickets->create([
                    'customer_id' => $customer->id,
                    'subject' => Str::limit(trim($data->subject), 250, ''),
                    'topic' => $data->topic,
                    'order_uuid' => $order?->uuid,
                    'order_number' => $order?->number,
                    'status' => TicketStatus::Open,
                    'last_reply_by' => TicketAuthor::Customer,
                    'last_reply_at' => now(),
                    'store_unread' => true,
                    'customer_unread' => false,
                ]);
                $this->tickets->update($ticket, ['reference' => 'C-'.(1000 + $ticket->id)]);

                $message = $this->storeMessage($ticket, $actor, $body, $data->files, $stored);
                $this->dispatchAfterCommit(new CustomerTicketOpened($ticket, $message));

                return $ticket;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }
    }

    /**
     * A message from the customer (actor Customer) or a store staff member (actor Vendor).
     */
    public function reply(CustomerTicket $ticket, TicketReplyData $reply): CustomerTicketMessage
    {
        $body = $reply->body !== null ? trim($reply->body) : '';
        $fromCustomer = $reply->actor->author === TicketAuthor::Customer;

        if ($body === '' && $reply->files === []) {
            throw SupportRuleException::emptyMessage();
        }

        if ($fromCustomer && ! $ticket->customerCanReply()) {
            throw SupportRuleException::ticketClosed();
        }

        $stored = [];

        try {
            $message = $this->transaction(function () use ($ticket, $reply, $body, $fromCustomer, &$stored): CustomerTicketMessage {
                $message = $this->storeMessage($ticket, $reply->actor, $body !== '' ? $body : null, $reply->files, $stored);
                $from = $ticket->status;
                $attributes = ['last_reply_by' => $reply->actor->author, 'last_reply_at' => now()];

                if ($fromCustomer) {
                    $attributes += ['store_unread' => true, 'customer_unread' => false];

                    if ($from !== TicketStatus::Open) {
                        $attributes += ['status' => TicketStatus::Open, 'resolved_at' => null];
                    }
                } else {
                    $attributes += ['customer_unread' => true, 'store_unread' => false];

                    if ($from === TicketStatus::Open || $from === TicketStatus::AwaitingReply) {
                        $attributes['status'] = TicketStatus::Answered;
                    } elseif ($from === TicketStatus::Closed) {
                        $attributes += ['status' => TicketStatus::Answered, 'closed_at' => null];
                    }
                }

                $this->tickets->update($ticket, $attributes);
                $this->dispatchAfterCommit(new CustomerTicketReplied($ticket, $message));

                return $message;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        return $message->load('attachments');
    }

    /**
     * Store staff: resolve, close or reopen.
     */
    public function changeStatus(CustomerTicket $ticket, TicketStatus $to, TicketActor $staff): CustomerTicket
    {
        if (! in_array($to, self::STAFF_STATUSES, true) || $to === $ticket->status) {
            throw SupportRuleException::noChanges();
        }

        if ($to === TicketStatus::Open && $ticket->status->isActive()) {
            throw SupportRuleException::notClosed();
        }

        $this->apply($ticket, $to, $staff, ['customer_unread' => true]);

        return $ticket->refresh();
    }

    /**
     * The customer closes their ticket.
     */
    public function closeByCustomer(CustomerTicket $ticket, Customer $customer): CustomerTicket
    {
        if ($ticket->status->isClosed()) {
            throw SupportRuleException::alreadyClosed();
        }

        $this->apply($ticket, TicketStatus::Closed, TicketActor::customer($customer), ['store_unread' => true]);

        return $ticket->refresh();
    }

    public function openByStore(CustomerTicket $ticket): void
    {
        if ($ticket->store_unread) {
            $this->tickets->update($ticket, ['store_unread' => false]);
        }
    }

    public function openByCustomer(CustomerTicket $ticket): void
    {
        if ($ticket->customer_unread) {
            $this->tickets->update($ticket, ['customer_unread' => false]);
        }
    }

    /**
     * @return Collection<int, CustomerTicketMessage>
     */
    public function messages(CustomerTicket $ticket, ?int $afterId = null): Collection
    {
        return $this->tickets->thread($ticket, $afterId);
    }

    /**
     * An attachment of the given ticket (null = any ticket of the store) or 404.
     */
    public function attachment(int $id, ?CustomerTicket $ticket = null): CustomerTicketAttachment
    {
        $file = $this->tickets->findAttachment($id);

        if (! $file instanceof CustomerTicketAttachment || ($ticket !== null && $file->ticket_id !== $ticket->id)) {
            throw new NotFoundHttpException;
        }

        return $file;
    }

    /**
     * Streams an attachment (inline for images / PDF unless $download).
     */
    public function stream(CustomerTicketAttachment $attachment, bool $download = false): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            throw new NotFoundHttpException((string) __('support::tickets.errors.attachment_missing'));
        }

        $headers = [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        $inline = ! $download && ($attachment->isImage() || $attachment->mime_type === 'application/pdf');

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name, $headers, 'inline')
            : $disk->download($attachment->path, $attachment->original_name, $headers);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function apply(CustomerTicket $ticket, TicketStatus $to, TicketActor $actor, array $extra): void
    {
        $from = $ticket->status;
        $attributes = [
            'status' => $to,
            'resolved_at' => $to === TicketStatus::Resolved ? now() : null,
            'closed_at' => $to === TicketStatus::Closed ? now() : null,
        ] + $extra;

        $this->transaction(function () use ($ticket, $attributes, $from, $to, $actor): void {
            $this->tickets->update($ticket, $attributes);
            $this->dispatchAfterCommit(new CustomerTicketStatusChanged($ticket, $from, $to, $actor->author, $actor->name));
        });
    }

    private function ownOrder(Customer $customer, string $uuid): Order
    {
        try {
            return $this->orders->find($customer, $uuid);
        } catch (CommerceException) {
            throw ValidationException::withMessages(['order_uuid' => (string) __('support::customer_tickets.errors.order')]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $stored
     */
    private function storeMessage(CustomerTicket $ticket, TicketActor $actor, ?string $body, array $files, array &$stored): CustomerTicketMessage
    {
        $message = $this->tickets->createMessage($ticket, [
            'author_type' => $actor->author,
            'author_id' => $actor->id,
            'author_name' => Str::limit($actor->name, 150, ''),
            'body' => $body,
        ]);
        $folder = 'customer-tickets/'.(int) tenant()?->getTenantKey().'/'.$ticket->id;

        foreach ($files as $file) {
            $path = $file->storeAs($folder, Str::uuid()->toString().'.'.($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'), self::DISK);

            if ($path === false) {
                throw SupportRuleException::attachmentFailed();
            }

            $stored[] = $path;
            $this->tickets->addAttachment($message, [
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }

        return $message;
    }

    /**
     * @param  list<string>  $paths
     */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
