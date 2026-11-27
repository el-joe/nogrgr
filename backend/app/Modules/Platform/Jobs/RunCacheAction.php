<?php

namespace App\Modules\Platform\Jobs;

use App\Modules\Platform\Enums\CacheRunStatus;
use App\Modules\Platform\Models\CacheClearRun;
use App\Modules\Platform\Repositories\Contracts\CacheClearRunRepositoryContract;
use App\Modules\Platform\Services\CacheToolsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A cache tool run that touches every store (ADM-114: static texts, all stores).
 */
class RunCacheAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $runId) {}

    public function handle(CacheClearRunRepositoryContract $runs, CacheToolsService $tools): void
    {
        $run = $runs->find($this->runId);

        if ($run instanceof CacheClearRun && $run->status === CacheRunStatus::Queued) {
            $tools->execute($run);
        }
    }
}
