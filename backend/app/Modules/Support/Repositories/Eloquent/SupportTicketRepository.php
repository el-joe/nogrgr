<?php

namespace App\Modules\Support\Repositories\Eloquent;

use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<SupportTicket>
 */
class SupportTicketRepository extends BaseRepository implements SupportTicketRepositoryContract
{
    public const TABS = ['open', 'waiting', 'resolved', 'closed', 'all'];

    protected function model(): string
    {
        return SupportTicket::class;
    }

    public function listing(array $filters, ?array $owner = null): Builder
    {
        $query = $this->scoped($owner)->with(['tenant:id,name,slug', 'assignee:id,name']);

        $tab = is_string($filters['tab'] ?? null) ? $filters['tab'] : null;

        if ($tab !== null && $tab !== 'all' && in_array($tab, self::TABS, true)) {
            $query->whereIn('status', array_map(static fn (TicketStatus $status): string => $status->value, TicketStatus::forTab($tab)));
        }

        if (($status = TicketStatus::tryFrom((string) ($filters['status'] ?? ''))) !== null) {
            $query->where('status', $status->value);
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->whereIn('status', [TicketStatus::Open->value, TicketStatus::AwaitingReply->value, TicketStatus::Answered->value]);
        }

        if (($priority = TicketPriority::tryFrom((string) ($filters['priority'] ?? ''))) !== null) {
            $query->where('priority', $priority->value);
        }

        if (($category = TicketCategory::tryFrom((string) ($filters['category'] ?? ''))) !== null) {
            $query->where('category', $category->value);
        }

        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);

        if ($tenantId !== false) {
            $query->where('tenant_id', $tenantId);
        }

        $assignee = $filters['assignee'] ?? null;

        if ($assignee === 'none') {
            $query->whereNull('assigned_admin_id');
        } elseif (($assigneeId = filter_var($assignee, FILTER_VALIDATE_INT)) !== false) {
            $query->where('assigned_admin_id', $assigneeId);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100)).'%';
            $query->where(static function (Builder $where) use ($like): void {
                $where->where('reference', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('opened_by_name', 'like', $like)
                    ->orWhereHas('tenant', static fn (Builder $tenant) => $tenant->where('name', 'like', $like)->orWhere('slug', 'like', $like));
            });
        }

        return $query;
    }

    public function tabCounts(): array
    {
        $rows = $this->query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $counts = array_fill_keys(self::TABS, 0);

        foreach ($rows as $status => $count) {
            $case = TicketStatus::tryFrom((string) $status);

            if ($case !== null) {
                $counts[$case->tab()] += (int) $count;
                $counts['all'] += (int) $count;
            }
        }

        return $counts;
    }

    public function findVisible(int $id, ?array $owner = null): ?SupportTicket
    {
        return $this->scoped($owner)->with(['tenant', 'assignee:id,name'])->find($id);
    }

    public function latestVisible(): ?SupportTicket
    {
        return $this->query()->with('tenant')->orderByDesc('last_reply_at')->orderByDesc('id')->first();
    }

    public function countOpenForAdmins(): int
    {
        return $this->query()->where('status', TicketStatus::Open->value)->count();
    }

    public function countUnreadForRequester(): int
    {
        return $this->query()->where('requester_unread', true)->where('status', '!=', TicketStatus::Closed->value)->count();
    }

    /**
     * @param  array{owner_id: int, tenant_ids: list<int>}|null  $owner
     * @return Builder<SupportTicket>
     */
    private function scoped(?array $owner): Builder
    {
        $query = $this->query();

        if ($owner !== null) {
            $query->where(static function (Builder $where) use ($owner): void {
                $where->where('owner_id', $owner['owner_id']);

                if ($owner['tenant_ids'] !== []) {
                    $where->orWhereIn('tenant_id', $owner['tenant_ids']);
                }
            });
        }

        return $query;
    }
}
