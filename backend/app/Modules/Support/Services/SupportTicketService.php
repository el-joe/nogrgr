<?php

namespace App\Modules\Support\Services;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Support\DTOs\OpenTicketData;
use App\Modules\Support\DTOs\TicketActor;
use App\Modules\Support\DTOs\TicketReplyData;
use App\Modules\Support\DTOs\TicketUpdateData;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Events\TicketAssigned;
use App\Modules\Support\Events\TicketOpened;
use App\Modules\Support\Events\TicketReplied;
use App\Modules\Support\Events\TicketStatusChanged;
use App\Modules\Support\Events\TicketThreadUpdated;
use App\Modules\Support\Exceptions\SupportRuleException;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Repositories\Contracts\SupportTicketMessageRepositoryContract;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Support tickets engine shared by the admin, vendor and owner surfaces: open a ticket,
 * post replies / internal notes with attachments (private central disk), status /
 * priority / assignee changes, close / reopen by the requester, unread flags, SLA and
 * the live "thread updated" push over Reverb. Domain events feed the notifications.
 */
class SupportTicketService extends BaseService
{
    /**
     * Private disk shared by the vendor panel (tenant context) and the central panels.
     */
    public const DISK = 'central';

    public const CHANNEL_EVENT = 'ticket.updated';

    public function __construct(
        private readonly SupportTicketRepositoryContract $tickets,
        private readonly SupportTicketMessageRepositoryContract $messages,
        private readonly AdminRepositoryContract $admins,
    ) {}

    /**
     * A ticket visible to the caller (tenant scope in a store; owner scope when given) or 404.
     *
     * @param  array{owner_id: int, tenant_ids: list<int>}|null  $owner
     */
    public function find(int $id, ?array $owner = null): SupportTicket
    {
        return $this->tickets->findVisible($id, $owner) ?? throw new NotFoundHttpException;
    }

    /**
     * The most recently active ticket visible here (vendor index opens it).
     */
    public function latest(): ?SupportTicket
    {
        return $this->tickets->latestVisible();
    }

    /**
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        return $this->tickets->tabCounts();
    }

    /**
     * Owner scope: tickets the owner opened and every ticket of their stores.
     *
     * @return array{owner_id: int, tenant_ids: list<int>}
     */
    public function ownerScope(Owner $owner): array
    {
        return ['owner_id' => (int) $owner->id, 'tenant_ids' => $owner->tenants()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()];
    }

    /**
     * Admins a ticket can be assigned to (active, allowed to view support tickets).
     *
     * @return array<int, string>
     */
    public function assigneeOptions(): array
    {
        return $this->admins->activeWithPermission('support.tickets.view')
            ->sortBy('name')
            ->mapWithKeys(static fn (Admin $admin): array => [(int) $admin->id => (string) $admin->name])
            ->all();
    }

    public function open(OpenTicketData $data): SupportTicket
    {
        $body = trim($data->body);

        if ($body === '') {
            throw SupportRuleException::emptyMessage();
        }

        $stored = [];

        try {
            $ticket = $this->transaction(function () use ($data, $body, &$stored): SupportTicket {
                $now = now();
                /** @var SupportTicket $ticket */
                $ticket = $this->tickets->create([
                    'tenant_id' => $data->tenantId,
                    'owner_id' => $data->ownerId,
                    'opened_by_type' => $data->actor->author,
                    'opened_by_id' => $data->actor->id,
                    'opened_by_name' => $data->actor->name,
                    'opened_by_email' => $data->actor->email,
                    'subject' => Str::limit(trim($data->subject), 250, ''),
                    'category' => $data->category,
                    'priority' => $data->priority,
                    'status' => TicketStatus::Open,
                    'last_reply_by' => $data->actor->author,
                    'last_reply_at' => $now,
                    'admin_unread' => true,
                    'requester_unread' => false,
                    'sla_due_at' => $now->copy()->addHours($data->priority->slaHours()),
                ]);
                $this->tickets->update($ticket, ['reference' => 'T-'.(1000 + $ticket->id)]);

                $message = $this->storeMessage($ticket, $data->actor, $body, $data->files, false, $stored);
                $this->dispatchAfterCommit(new TicketOpened($ticket, $message));

                return $ticket;
            }, $this->connection());
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        return $ticket;
    }

    public function reply(SupportTicket $ticket, TicketReplyData $reply): SupportTicketMessage
    {
        $body = $reply->body !== null ? trim($reply->body) : '';
        $requester = $reply->actor->author->isRequester();
        $internal = $reply->internal && ! $requester;

        if ($body === '' && $reply->files === []) {
            throw SupportRuleException::emptyMessage();
        }

        if ($requester && $ticket->status->isClosed()) {
            throw SupportRuleException::ticketClosed();
        }

        $stored = [];

        try {
            $message = $this->transaction(function () use ($ticket, $reply, $body, $requester, $internal, &$stored): SupportTicketMessage {
                $message = $this->storeMessage($ticket, $reply->actor, $body !== '' ? $body : null, $reply->files, $internal, $stored);

                if ($internal) {
                    return $message;
                }

                $from = $ticket->status;
                $now = now();
                $attributes = ['last_reply_by' => $reply->actor->author, 'last_reply_at' => $now];

                if ($requester) {
                    $attributes += [
                        'admin_unread' => true,
                        'requester_unread' => false,
                        'sla_due_at' => $now->copy()->addHours($ticket->priority->slaHours()),
                    ];

                    if ($from !== TicketStatus::Open) {
                        $attributes += ['status' => TicketStatus::Open, 'resolved_at' => null];
                    }
                } else {
                    $attributes += ['requester_unread' => true, 'admin_unread' => false, 'sla_due_at' => null];

                    if ($from === TicketStatus::Open) {
                        $attributes['status'] = TicketStatus::Answered;
                    } elseif ($from === TicketStatus::Closed) {
                        $attributes += ['status' => TicketStatus::Answered, 'closed_at' => null];
                    }
                }

                $this->tickets->update($ticket, $attributes);
                $this->dispatchAfterCommit(new TicketReplied($ticket, $message));

                return $message;
            }, $this->connection());
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        $this->push($ticket, 'message', $message->id);

        return $message->load('attachments');
    }

    /**
     * Admin change of status / priority / assignee (only what was sent).
     */
    public function update(SupportTicket $ticket, TicketUpdateData $data, TicketActor $admin): SupportTicket
    {
        $attributes = [];
        $events = [];
        $from = $ticket->status;

        if ($data->status !== null && $data->status !== $ticket->status) {
            $attributes += $this->statusAttributes($ticket, $data->status);
            $events[] = new TicketStatusChanged($ticket, $from, $data->status, TicketAuthor::Admin, $admin->name);
        }

        if ($data->priority !== null && $data->priority !== $ticket->priority) {
            $attributes['priority'] = $data->priority;

            if (($attributes['status'] ?? $ticket->status) === TicketStatus::Open) {
                $attributes['sla_due_at'] = ($ticket->last_reply_at ?? now())->copy()->addHours($data->priority->slaHours());
            }
        }

        if ($data->assigneeGiven && $data->assigneeId !== $ticket->assigned_admin_id) {
            $attributes['assigned_admin_id'] = $data->assigneeId;

            if ($data->assigneeId !== null && $data->assigneeId !== $admin->id) {
                $events[] = new TicketAssigned($ticket, $data->assigneeId, $admin->name);
            }
        }

        if ($attributes === []) {
            throw SupportRuleException::noChanges();
        }

        $this->transaction(function () use ($ticket, $attributes, $events): void {
            $this->tickets->update($ticket, $attributes);

            foreach ($events as $event) {
                $this->dispatchAfterCommit($event);
            }
        }, $this->connection());

        $this->push($ticket, 'status');

        return $ticket->refresh();
    }

    /**
     * The requester closes their ticket.
     */
    public function close(SupportTicket $ticket, TicketActor $actor): void
    {
        if ($ticket->status->isClosed()) {
            throw SupportRuleException::alreadyClosed();
        }

        $this->changeByRequester($ticket, TicketStatus::Closed, $actor);
    }

    /**
     * The requester reopens a closed / resolved ticket.
     */
    public function reopen(SupportTicket $ticket, TicketActor $actor): void
    {
        if ($ticket->status->isActive()) {
            throw SupportRuleException::notClosed();
        }

        $this->changeByRequester($ticket, TicketStatus::Open, $actor);
    }

    /**
     * An admin opened the ticket: requester messages count as read.
     */
    public function openByAdmin(SupportTicket $ticket): void
    {
        if ($ticket->admin_unread) {
            $this->tickets->update($ticket, ['admin_unread' => false]);
        }
    }

    /**
     * The store / owner opened the ticket: platform replies count as read.
     */
    public function openByRequester(SupportTicket $ticket): void
    {
        if ($ticket->requester_unread) {
            $this->tickets->update($ticket, ['requester_unread' => false]);
        }
    }

    /**
     * @return Collection<int, SupportTicketMessage>
     */
    public function messages(SupportTicket $ticket, ?int $afterId = null, bool $withInternal = false): Collection
    {
        return $this->messages->thread($ticket, $afterId, $withInternal);
    }

    /**
     * @return Collection<int, SupportTicketAttachment>
     */
    public function attachments(SupportTicket $ticket, bool $withInternal = false): Collection
    {
        return $this->messages->attachments($ticket, $withInternal);
    }

    public function findAttachment(int $id): ?SupportTicketAttachment
    {
        return $this->messages->findAttachment($id);
    }

    /**
     * Streams an attachment (inline for images / PDF unless $download).
     */
    public function stream(SupportTicketAttachment $attachment, bool $download = false): StreamedResponse
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
     * SLA of an open ticket: time left (or overdue) to answer the requester's last message.
     *
     * @return array{label: string, tone: string}|null
     */
    public function sla(SupportTicket $ticket, ?Carbon $now = null): ?array
    {
        if ($ticket->status !== TicketStatus::Open || $ticket->sla_due_at === null) {
            return null;
        }

        $now ??= now();
        $minutes = (int) floor($now->diffInMinutes($ticket->sla_due_at, false));
        $span = self::span(abs($minutes));

        return $minutes >= 0
            ? ['label' => (string) __('support::tickets.sla.left', ['time' => $span]), 'tone' => $minutes <= 60 ? 'warning' : 'success']
            : ['label' => (string) __('support::tickets.sla.overdue', ['time' => $span]), 'tone' => 'danger'];
    }

    /**
     * Reverb (Pusher protocol) settings of the live thread for the admin or vendor page, or
     * null when broadcasting is not Reverb (the page polls instead).
     *
     * @return array{key: string, host: string, port: int, tls: bool, channel: string, authUrl: string, event: string}|null
     */
    public function realtimeConfig(SupportTicket $ticket, string $surface = 'admin'): ?array
    {
        $key = config('broadcasting.connections.reverb.key');
        $authRoute = $surface === 'vendor' ? 'vendor.support.broadcast-auth' : 'admin.notifications.broadcast-auth';

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || $key === '' || ! Route::has($authRoute)) {
            return null;
        }

        $scheme = (string) config('broadcasting.connections.reverb.options.scheme', 'https');

        return [
            'key' => $key,
            'host' => (string) (config('broadcasting.connections.reverb.options.host') ?: request()->getHost()),
            'port' => (int) config('broadcasting.connections.reverb.options.port', $scheme === 'https' ? 443 : 80),
            'tls' => $scheme === 'https',
            'channel' => 'private-support.tickets.'.$ticket->id,
            'authUrl' => $surface === 'vendor' ? vendor_route('support.broadcast-auth') : route($authRoute),
            'event' => self::CHANNEL_EVENT,
        ];
    }

    private function changeByRequester(SupportTicket $ticket, TicketStatus $to, TicketActor $actor): void
    {
        $from = $ticket->status;
        $attributes = $this->statusAttributes($ticket, $to);

        if ($to === TicketStatus::Open) {
            $attributes += ['admin_unread' => true, 'sla_due_at' => now()->addHours($ticket->priority->slaHours())];
        }

        $this->transaction(function () use ($ticket, $attributes, $from, $to, $actor): void {
            $this->tickets->update($ticket, $attributes);
            $this->dispatchAfterCommit(new TicketStatusChanged($ticket, $from, $to, $actor->author, $actor->name));
        }, $this->connection());

        $this->push($ticket, 'status');
    }

    /**
     * @return array<string, mixed>
     */
    private function statusAttributes(SupportTicket $ticket, TicketStatus $to): array
    {
        $attributes = ['status' => $to];

        $attributes['resolved_at'] = $to === TicketStatus::Resolved ? now() : null;
        $attributes['closed_at'] = $to === TicketStatus::Closed ? now() : null;

        if ($to !== TicketStatus::Open) {
            $attributes['sla_due_at'] = null;
        } elseif ($ticket->sla_due_at === null) {
            $attributes['sla_due_at'] = now()->addHours($ticket->priority->slaHours());
        }

        return $attributes;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $stored  paths written, for clean-up on failure
     */
    private function storeMessage(SupportTicket $ticket, TicketActor $actor, ?string $body, array $files, bool $internal, array &$stored): SupportTicketMessage
    {
        $message = $this->messages->createFor($ticket, [
            'author_type' => $actor->author,
            'author_id' => $actor->id,
            'author_name' => $actor->name,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        foreach ($files as $file) {
            $path = $file->storeAs('support/tickets/'.$ticket->id, Str::uuid()->toString().'.'.($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'), self::DISK);

            if ($path === false) {
                throw SupportRuleException::attachmentFailed();
            }

            $stored[] = $path;
            $this->messages->addAttachment($message, [
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

    private function push(SupportTicket $ticket, string $reason, int $lastMessageId = 0): void
    {
        event(new TicketThreadUpdated((int) $ticket->id, $lastMessageId, $reason));
    }

    private function connection(): ?string
    {
        return (new SupportTicket)->getConnectionName();
    }

    private static function span(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours >= 24) {
            return (string) __('support::tickets.sla.days', ['days' => intdiv($hours, 24), 'hours' => $hours % 24]);
        }

        return $hours > 0
            ? (string) __('support::tickets.sla.hours', ['hours' => $hours, 'minutes' => $rest])
            : (string) __('support::tickets.sla.minutes', ['minutes' => $rest]);
    }
}
