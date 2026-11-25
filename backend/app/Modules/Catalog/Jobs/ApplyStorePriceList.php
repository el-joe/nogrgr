<?php

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Services\Vendor\StorePriceListService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Applies a price list markup run (VEN-010a). Dispatched in tenant context, so the queue
 * tenancy bootstrapper re-initializes the store before handle().
 */
class ApplyStorePriceList implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly string $runId) {}

    public function handle(StorePriceListService $service): void
    {
        $run = $service->find($this->runId);

        if ($run !== null) {
            $service->process($run);
        }
    }
}
