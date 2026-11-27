<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message on a customer ticket: from the customer (→ store staff) or from the store
 * (→ the customer: inbox, e-mail, push).
 */
final class CustomerTicketReplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CustomerTicket $ticket,
        public readonly CustomerTicketMessage $message,
    ) {}
}
