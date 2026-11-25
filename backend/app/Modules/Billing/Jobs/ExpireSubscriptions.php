<?php

namespace App\Modules\Billing\Jobs;

use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Scheduled hourly (routes/console.php): moves lapsed subscriptions to past_due /
 * expired (SubscriptionService::processLapsed).
 */
final class ExpireSubscriptions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $uniqueFor = 3600;

    public function handle(SubscriptionService $subscriptions): void
    {
        $subscriptions->processLapsed(now());
    }
}
