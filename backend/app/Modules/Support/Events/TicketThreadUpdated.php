<?php

namespace App\Modules\Support\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live refresh of an open ticket page over Reverb: private channel "support.tickets.<id>"
 * (authorised in the Support provider). Pages then fetch new messages themselves; no
 * message content (internal notes included) travels on the socket.
 */
final class TicketThreadUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $ticketId,
        public readonly int $lastMessageId,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('support.tickets.'.$this->ticketId);
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * @return array{id: int, last_message_id: int, reason: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->ticketId, 'last_message_id' => $this->lastMessageId, 'reason' => $this->reason];
    }
}
