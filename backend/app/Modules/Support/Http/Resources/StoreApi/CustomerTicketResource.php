<?php

namespace App\Modules\Support\Http\Resources\StoreApi;

use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketAttachment;
use App\Modules\Support\Models\CustomerTicketMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Customer ticket JSON for the Store API (app + theme account): row (list) and detail
 * (messages with files behind 30-minute signed URLs). Labels in the request language.
 */
final class CustomerTicketResource
{
    public const FILE_URL_MINUTES = 30;

    /**
     * @return array<string, mixed>
     */
    public static function row(CustomerTicket $ticket): array
    {
        $latest = $ticket->relationLoaded('latestMessage') ? $ticket->latestMessage : null;

        return [
            'id' => $ticket->id,
            'reference' => $ticket->displayReference(),
            'subject' => $ticket->subject,
            'topic' => ['value' => $ticket->topic->value, 'label' => __st('support.subjects.'.$ticket->topic->value)],
            'status' => ['value' => $ticket->status->value, 'label' => $ticket->status->requesterLabel(), 'tone' => $ticket->status->tone()],
            'order' => $ticket->order_uuid !== null ? ['uuid' => $ticket->order_uuid, 'number' => $ticket->order_number] : null,
            'unread' => $ticket->customer_unread,
            'last_reply_by' => $ticket->last_reply_by->value === 'customer' ? 'customer' : 'store',
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            'excerpt' => $latest !== null ? Str::limit(trim((string) $latest->body), 120) : null,
            'can_reply' => $ticket->customerCanReply(),
            'can_close' => ! $ticket->status->isClosed(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, CustomerTicketMessage>  $messages
     * @return array<string, mixed>
     */
    public static function detail(CustomerTicket $ticket, Collection $messages): array
    {
        return self::row($ticket) + [
            'messages' => $messages->map(static fn (CustomerTicketMessage $message): array => self::message($ticket, $message))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(CustomerTicket $ticket, CustomerTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'author' => $message->isFromCustomer() ? 'customer' : 'store',
            'author_name' => $message->author_name,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->attachments->map(static fn (CustomerTicketAttachment $file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'size_label' => $file->humanSize(),
                'is_image' => $file->isImage(),
                'url' => url(URL::temporarySignedRoute('store-api.v1.support.tickets.attachments', now()->addMinutes(self::FILE_URL_MINUTES), ['ticket' => $ticket->id, 'attachment' => $file->id], absolute: false)),
            ])->values()->all(),
        ];
    }
}
