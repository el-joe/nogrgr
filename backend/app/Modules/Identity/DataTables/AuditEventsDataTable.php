<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\DTOs\AuditFilterData;
use App\Modules\Identity\Services\AuditLogService;
use App\Modules\Identity\Support\AuditPresenter;
use App\Modules\Platform\Models\AuditEvent;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Audit log list (server-side, newest first). Filters: search (action,
 * subject, IP, URL), actor (admin), tenant, event, date range.
 *
 * @extends BaseDataTable<AuditEvent>
 */
final class AuditEventsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'actor', 'tenant', 'event', 'date_from', 'date_to'];

    public function __construct(private readonly AuditLogService $audit) {}

    protected function query(): Builder
    {
        return $this->audit->query(new AuditFilterData);
    }

    /**
     * Filters are applied by AuditLogService::query() (shared with the CSV export).
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->audit->query(AuditFilterData::fromFilters($filters));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('id', $order === 'asc' ? 'asc' : 'desc'))
            ->editColumn('created_at', function (AuditEvent $event): string {
                $local = $this->audit->localTime($event->created_at);

                return $local === null ? '' : '<span class="grid"><time class="font-medium text-ink" datetime="'.e($local->toIso8601String()).'">'.e($local->isoFormat('D MMM HH:mm')).'</time><span class="text-xs text-ink-secondary">'.e($local->diffForHumans()).'</span></span>';
            })
            ->addColumn('actor', fn (AuditEvent $event): string => '<span class="grid"><span class="font-medium text-ink">'.e($this->audit->actorLabel($event)).'</span><span class="text-xs text-ink-secondary">'.e($event->guard !== null ? __('identity::audit.guards.'.$event->guard) : __('identity::audit.system_actor')).'</span></span>')
            ->editColumn('action', fn (AuditEvent $event): string => '<span class="grid"><span class="text-ink">'.e(AuditPresenter::actionLabel($event->action)).'</span><bdi dir="ltr" class="text-xs text-ink-muted">'.e($event->action).'</bdi></span>')
            ->addColumn('subject', fn (AuditEvent $event): string => view('identity::admin.audit._subject-cell', ['event' => $event, 'tenant' => $this->audit->tenantLabel($event->tenant_id)])->render())
            ->editColumn('ip_address', fn (AuditEvent $event): string => '<bdi dir="ltr" class="tabular-nums">'.e($event->ip_address ?? '—').'</bdi>')
            ->addColumn('actions', fn (AuditEvent $event): string => view('identity::admin.audit._actions-cell', ['event' => $event])->render())
            ->rawColumns(['created_at', 'actor', 'action', 'subject', 'ip_address', 'actions']);
    }
}
