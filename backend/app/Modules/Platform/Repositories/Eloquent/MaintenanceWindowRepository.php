<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\MaintenanceScope;
use App\Modules\Platform\Enums\MaintenanceWindowStatus;
use App\Modules\Platform\Models\MaintenanceWindow;
use App\Modules\Platform\Repositories\Contracts\MaintenanceWindowRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<MaintenanceWindow>
 */
class MaintenanceWindowRepository extends BaseRepository implements MaintenanceWindowRepositoryContract
{
    protected function model(): string
    {
        return MaintenanceWindow::class;
    }

    public function listing(array $filters = []): Builder
    {
        $now = now();
        $status = MaintenanceWindowStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $scope = MaintenanceScope::tryFrom(is_string($filters['scope'] ?? null) ? $filters['scope'] : '');

        return MaintenanceWindow::withoutTenantScope()
            ->with('tenant:id,name,slug')
            ->when($scope !== null, fn (Builder $query) => $query->where('scope', $scope?->value))
            ->when($status === MaintenanceWindowStatus::Scheduled, fn (Builder $query) => $query->whereNull('ended_at')->where('starts_at', '>', $now))
            ->when($status === MaintenanceWindowStatus::Active, fn (Builder $query) => $query->whereNull('ended_at')->where('starts_at', '<=', $now)
                ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', $now)))
            ->when($status === MaintenanceWindowStatus::Completed, fn (Builder $query) => $query->where(fn (Builder $done) => $done
                ->where(fn (Builder $ended) => $ended->whereNotNull('ended_at')->whereColumn('ended_at', '>', 'starts_at'))
                ->orWhere(fn (Builder $over) => $over->whereNull('ended_at')->where('ends_at', '<=', $now))))
            ->when($status === MaintenanceWindowStatus::Cancelled, fn (Builder $query) => $query->whereNotNull('ended_at')->whereColumn('ended_at', '<=', 'starts_at'));
    }

    public function unfinished(): Collection
    {
        return MaintenanceWindow::withoutTenantScope()
            ->whereNull('ended_at')
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('starts_at')
            ->get();
    }
}
