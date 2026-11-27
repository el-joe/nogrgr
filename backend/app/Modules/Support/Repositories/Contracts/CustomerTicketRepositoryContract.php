<?php

namespace App\Modules\Support\Repositories\Contracts;

use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketAttachment;
use App\Modules\Support\Models\CustomerTicketMessage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer tickets of the current store (store database).
 *
 * @extends RepositoryContract<CustomerTicket>
 */
interface CustomerTicketRepositoryContract extends RepositoryContract
{
    /**
     * One customer's tickets, newest activity first; $group = active | closed | null (all).
     *
     * @return LengthAwarePaginator<int, CustomerTicket>
     */
    public function forCustomer(int $customerId, ?string $group, int $perPage): LengthAwarePaginator;

    public function findForCustomer(int $customerId, int $id): ?CustomerTicket;

    /**
     * Staff list query (Vendor › Support › Customers): search (reference, subject, customer
     * name / e-mail) and status (a TicketStatus value or "active").
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<CustomerTicket>
     */
    public function listing(array $filters): Builder;

    public function latest(): ?CustomerTicket;

    public function unreadForStore(): int;

    /**
     * @return Collection<int, CustomerTicketMessage>
     */
    public function thread(CustomerTicket $ticket, ?int $afterId = null): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMessage(CustomerTicket $ticket, array $attributes): CustomerTicketMessage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addAttachment(CustomerTicketMessage $message, array $attributes): CustomerTicketAttachment;

    public function findAttachment(int $id): ?CustomerTicketAttachment;
}
