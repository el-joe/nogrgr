<?php

namespace App\Modules\Support\DTOs;

use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin change of a ticket's status / priority / assignee (only the given fields change;
 * `assigneeGiven` distinguishes "unassign" (null) from "not sent").
 */
final class TicketUpdateData extends BaseDTO
{
    public function __construct(
        public readonly ?TicketStatus $status = null,
        public readonly ?TicketPriority $priority = null,
        public readonly bool $assigneeGiven = false,
        public readonly ?int $assigneeId = null,
    ) {}
}
