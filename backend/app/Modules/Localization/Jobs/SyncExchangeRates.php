<?php

namespace App\Modules\Localization\Jobs;

use App\Modules\Localization\Services\CurrencyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refresh every currency rate from open.er-api.com (USD base). Scheduled daily at
 * CurrencyService::SYNC_AT and dispatched by the admin "Sync rates now" button.
 */
class SyncExchangeRates implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(CurrencyService $currencies): void
    {
        $currencies->syncRates();
    }
}
