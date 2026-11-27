<?php

namespace App\Modules\Support\DataTables;

use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Repositories\Contracts\CustomerTicketRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Support › Customers list: one item column (reference · subject, customer, topic,
 * time, status, unread dot) linking to the conversation; search + status filter.
 *
 * @extends BaseDataTable<CustomerTicket>
 */
final class CustomerTicketsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status'];

    private ?int $selectedId = null;

    public function __construct(private readonly CustomerTicketRepositoryContract $tickets) {}

    public function selected(?int $id): self
    {
        $this->selectedId = $id;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->tickets->listing([]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->tickets->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('ticket', static fn (Builder $query, string $order) => $query->orderBy('last_reply_at', $order === 'asc' ? 'asc' : 'desc'))
            ->addColumn('ticket', fn (CustomerTicket $ticket): string => view('support::customers.cells.item', [
                'ticket' => $ticket,
                'selected' => $this->selectedId === $ticket->id,
            ])->render())
            ->rawColumns(['ticket']);
    }
}
