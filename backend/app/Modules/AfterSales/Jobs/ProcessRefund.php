<?php

namespace App\Modules\AfterSales\Jobs;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one refund to its payment gateway (central context; the order's store is
 * entered by RefundService). A gateway failure is recorded on the refund, not retried
 * here: staff retry from the refunds list.
 */
class ProcessRefund implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $refundId, public readonly Actor $actor) {}

    public function handle(RefundRepositoryContract $refunds, RefundService $service): void
    {
        $refund = $refunds->query()->withoutGlobalScopes()->find($this->refundId);

        if ($refund !== null) {
            $service->process($refund, $this->actor);
        }
    }
}
