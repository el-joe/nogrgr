<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\DTOs\AuditFilterData;
use App\Modules\Identity\Services\VendorAuditLogService;
use App\Modules\Identity\Support\AuditPresenter;
use App\Modules\Platform\Models\AuditEvent;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › Audit log list (VEN-086, server-side, newest first): when, member,
 * action, masked IP. Filters: search, member, area, date range.
 *
 * @extends BaseDataTable<AuditEvent>
 */
final class VendorAuditEventsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'actor', 'area', 'date_from', 'date_to'];

    public function __construct(private readonly VendorAuditLogService $audit) {}

    protected function query(): Builder
    {
        return $this->audit->query(new AuditFilterData);
    }

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

                return $local === null ? '' : '<span class="grid"><time class="font-semibold text-ink" datetime="'.e($local->toIso8601String()).'">'.e($local->isoFormat('D MMM HH:mm')).'</time><span class="text-xs text-ink-secondary">'.e($local->diffForHumans()).'</span></span>';
            })
            ->addColumn('member', function (AuditEvent $event): string {
                $actor = $this->audit->actor($event);

                return '<span class="grid"><span class="font-medium text-ink">'.e($actor['name']).'</span><span class="text-xs text-ink-secondary">'.e($actor['role']).'</span></span>';
            })
            ->editColumn('action', fn (AuditEvent $event): string => '<span class="grid"><span class="text-ink">'.e(AuditPresenter::actionLabel($event->action)).'</span>'
                .($event->subject_type !== null ? '<span class="text-xs text-ink-secondary">'.e(AuditPresenter::subjectLabel($event->subject_type)).($event->subject_id !== null ? ' <bdi dir="ltr">#'.e($event->subject_id).'</bdi>' : '').'</span>' : '')
                .'</span>')
            ->editColumn('ip_address', fn (AuditEvent $event): string => '<bdi dir="ltr" class="tabular-nums">'.e(VendorAuditLogService::maskIp($event->ip_address)).'</bdi>')
            ->rawColumns(['created_at', 'member', 'action', 'ip_address'])
            ->only(['id', 'created_at', 'member', 'action', 'ip_address']);
    }
}
