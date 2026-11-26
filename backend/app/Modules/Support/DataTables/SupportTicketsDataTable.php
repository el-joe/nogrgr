<?php

namespace App\Modules\Support\DataTables;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Support tickets list, one class for the three surfaces:
 *  - admin (ADM-116, Figma 80:95200): ticket · category, store, priority, assignee,
 *    updated, status, actions;
 *  - vendor (VEN-091, 88:111731): one "My tickets" item column (the store's tickets via
 *    the tenant scope), linking to the thread;
 *  - owner (OWN-009): ticket, store, updated, status — the owner's tickets and their stores'.
 *
 * @extends BaseDataTable<SupportTicket>
 */
final class SupportTicketsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'tab', 'status', 'priority', 'category', 'tenant_id', 'assignee'];

    private string $surface = 'admin';

    /**
     * @var array{owner_id: int, tenant_ids: list<int>}|null
     */
    private ?array $owner = null;

    private bool $canManage = false;

    private ?int $selectedId = null;

    public function __construct(private readonly SupportTicketRepositoryContract $tickets) {}

    /**
     * @param  array{owner_id: int, tenant_ids: list<int>}|null  $owner
     */
    public function for(string $surface, ?array $owner = null, bool $canManage = false, ?int $selectedId = null): self
    {
        $this->surface = $surface;
        $this->owner = $owner;
        $this->canManage = $canManage;
        $this->selectedId = $selectedId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->tickets->listing([], $this->owner);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->tickets->listing($filters, $this->owner);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $view = fn (string $name, SupportTicket $ticket): string => view('support::tickets.cells.'.$name, [
            'ticket' => $ticket,
            'surface' => $this->surface,
            'canManage' => $this->canManage,
            'selected' => $this->selectedId === $ticket->id,
        ])->render();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('ticket', static fn (Builder $query, string $order) => $query->orderBy('id', $direction($order)))
            ->orderColumn('store', static fn (Builder $query, string $order) => $query->orderBy('tenant_id', $direction($order)))
            ->orderColumn('priority', static fn (Builder $query, string $order) => $query->orderByRaw("case priority when 'urgent' then 3 when 'high' then 2 when 'normal' then 1 else 0 end ".$direction($order)))
            ->orderColumn('assignee', static fn (Builder $query, string $order) => $query->orderBy('assigned_admin_id', $direction($order)))
            ->orderColumn('updated', static fn (Builder $query, string $order) => $query->orderBy('last_reply_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('ticket', fn (SupportTicket $ticket): string => $view($this->surface === 'vendor' ? 'item' : 'ticket', $ticket))
            ->addColumn('store', fn (SupportTicket $ticket): string => $view('store', $ticket))
            ->addColumn('priority', fn (SupportTicket $ticket): string => $view('priority', $ticket))
            ->addColumn('assignee', fn (SupportTicket $ticket): string => e($ticket->assignee?->name ?? '—'))
            ->addColumn('updated', fn (SupportTicket $ticket): string => $view('updated', $ticket))
            ->addColumn('status', fn (SupportTicket $ticket): string => $view('status', $ticket))
            ->addColumn('actions', fn (SupportTicket $ticket): string => $view('actions', $ticket))
            ->rawColumns(['ticket', 'store', 'priority', 'updated', 'status', 'actions']);
    }
}
