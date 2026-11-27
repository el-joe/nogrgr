<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Models\MaintenanceWindow;
use App\Modules\Platform\Repositories\Contracts\MaintenanceWindowRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › System tools › Maintenance windows (ADM-113): window, scope, starts,
 * duration, status, actions. Filters: status, scope.
 *
 * @extends BaseDataTable<MaintenanceWindow>
 */
final class MaintenanceWindowsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'scope'];

    public function __construct(private readonly MaintenanceWindowRepositoryContract $windows) {}

    protected function query(): Builder
    {
        return $this->windows->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->windows->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->orderColumn('starts_at', 'starts_at $1, id $1')
            ->filterColumn('title', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.addcslashes($keyword, '%_\\').'%')
                ->orWhere('notes', 'like', '%'.addcslashes($keyword, '%_\\').'%')))
            ->editColumn('title', fn (MaintenanceWindow $window): string => view('platform::admin.system.maintenance._window-cell', ['window' => $window])->render())
            ->addColumn('scope', fn (MaintenanceWindow $window): string => view('platform::admin.system.maintenance._scope-cell', ['window' => $window])->render())
            ->editColumn('starts_at', fn (MaintenanceWindow $window): string => '<span class="whitespace-nowrap">'.e($window->starts_at->locale(app()->getLocale())->translatedFormat('j M H:i')).'</span>')
            ->addColumn('duration', fn (MaintenanceWindow $window): string => ($minutes = $window->durationMinutes()) === null
                ? '<span class="text-ink-secondary">'.e(__('platform::maintenance.until_ended')).'</span>'
                : e(__('platform::maintenance.minutes', ['count' => Number::format($minutes, locale: Locale::numberLocale())])))
            ->addColumn('status', fn (MaintenanceWindow $window): string => view('platform::admin.compliance._badge-cell', ['tone' => $window->status()->tone(), 'label' => $window->status()->label()])->render())
            ->addColumn('actions', fn (MaintenanceWindow $window): string => view('platform::admin.system.maintenance._actions', ['window' => $window])->render())
            ->rawColumns(['title', 'scope', 'starts_at', 'duration', 'status', 'actions'])
            ->only(['id', 'title', 'scope', 'starts_at', 'duration', 'status', 'actions']);
    }
}
