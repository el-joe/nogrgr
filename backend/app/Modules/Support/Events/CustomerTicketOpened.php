<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer opened a ticket with the store (→ store staff with support.view).
 */
final class CustomerTicketOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CustomerTicket $ticket,
        public readonly CustomerTicketMessage $message,
    ) {}
}
