<?php

namespace App\Modules\Support\Events;

use App\Modules\Support\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was assigned to an admin (not fired when it is unassigned).
 */
final class TicketAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly int $adminId,
        public readonly string $byName,
    ) {}
}
