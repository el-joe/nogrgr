<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A public reply was posted on a ticket (admin → requester or requester → platform).
 * Internal notes never fire it.
 */
final class TicketReplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
    ) {}
}
