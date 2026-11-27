<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\MaintenanceWindow;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Maintenance windows (central). Reads ignore the tenant scope: platform-wide windows
 * (tenant_id null) apply inside every store.
 *
 * @extends RepositoryContract<MaintenanceWindow>
 */
interface MaintenanceWindowRepositoryContract extends RepositoryContract
{
    /**
     * Admin table: every window with its store, newest start first.
     *
     * @param  array<string, mixed>  $filters  status (scheduled|active|completed|cancelled), scope
     * @return Builder<MaintenanceWindow>
     */
    public function listing(array $filters = []): Builder;

    /**
     * Windows not ended yet (scheduled or running) — the set the middleware evaluates.
     *
     * @return Collection<int, MaintenanceWindow>
     */
    public function unfinished(): Collection;
}
