<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\CustomerTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer ticket changed status: by the store (resolved / closed / reopened → the
 * customer) or by the customer closing it (→ store staff).
 */
final class CustomerTicketStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CustomerTicket $ticket,
        public readonly TicketStatus $from,
        public readonly TicketStatus $to,
        public readonly TicketAuthor $by,
        public readonly string $byName,
    ) {}
}
