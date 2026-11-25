<?php

namespace App\Modules\Catalog\Events\Requests;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live refresh of an open request page over Reverb: private channel
 * "requests.<kind>.<id>" (authorised in the Catalog provider). The page then fetches
 * the new messages / state; no message content travels on the socket.
 */
final class RequestThreadUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly string $kind,
        public readonly int $requestId,
        public readonly int $lastMessageId,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('requests.'.$this->kind.'.'.$this->requestId);
    }

    public function broadcastAs(): string
    {
        return 'request.updated';
    }

    /**
     * @return array{kind: string, id: int, last_message_id: int, reason: string}
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'id' => $this->requestId, 'last_message_id' => $this->lastMessageId, 'reason' => $this->reason];
    }
}
