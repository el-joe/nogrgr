<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The status of a ticket changed: by an admin (status select / Resolve) or by the
 * requester (close / reopen).
 */
final class TicketStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly TicketStatus $from,
        public readonly TicketStatus $to,
        public readonly TicketAuthor $by,
        public readonly string $byName,
    ) {}
}
