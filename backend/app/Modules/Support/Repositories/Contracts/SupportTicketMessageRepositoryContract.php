<?php

namespace App\Modules\Support\Repositories\Contracts;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<\App\Modules\Support\Models\SupportTicketMessage>
 */
interface SupportTicketMessageRepositoryContract extends RepositoryContract
{
    /**
     * Messages of a ticket, oldest first, after an id; internal notes only for admins.
     *
     * @return Collection<int, SupportTicketMessage>
     */
    public function thread(SupportTicket $ticket, ?int $afterId = null, bool $withInternal = false): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(SupportTicket $ticket, array $attributes): SupportTicketMessage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addAttachment(SupportTicketMessage $message, array $attributes): SupportTicketAttachment;

    /**
     * @return Collection<int, SupportTicketAttachment>
     */
    public function attachments(SupportTicket $ticket, bool $withInternal = false): Collection;

    public function findAttachment(int $id): ?SupportTicketAttachment;
}
