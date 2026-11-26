<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store member or an owner opened a support ticket (with its first message).
 */
final class TicketOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
    ) {}
}
