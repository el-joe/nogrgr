<?php

namespace App\Modules\Localization\Listeners;

use App\Modules\Billing\Events\LanguagePurchasePaid;
use App\Modules\Localization\Services\StoreLanguageService;

/**
 * A bought language was paid (settings page, setup wizard, webhook or admin): record its
 * paid AI translation run as queued and announce it (StoreTranslationRunQueued, P10).
 */
class QueueStoreTranslationRun
{
    public function __construct(private readonly StoreLanguageService $languages) {}

    public function handle(LanguagePurchasePaid $event): void
    {
        $this->languages->queueRun($event->tenantId, $event->languageId, $event->languageCode, $event->addonPurchaseId);
    }
}
