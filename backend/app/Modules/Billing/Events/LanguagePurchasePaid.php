<?php

namespace App\Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A store paid for an extra language (decision #45). P10 (Ai) listens and starts the
 * whole-store AI translation run; Localization enables the language when it finishes.
 * Plain scalars so listeners need no Billing model.
 */
final class LanguagePurchasePaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $languageId,
        public readonly string $languageCode,
        public readonly int $addonPurchaseId,
    ) {}
}
