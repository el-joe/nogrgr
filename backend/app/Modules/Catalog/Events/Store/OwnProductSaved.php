<?php

namespace App\Modules\Catalog\Events\Store;

use App\Modules\Ai\Contracts\AiTranslatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A vendor-own product was created or its content changed (fired after commit). Listeners:
 * AI translation of the missing locales (P10, decision #44) and the storefront cache (P7).
 *
 * @see AiTranslatable
 */
final class OwnProductSaved
{
    use Dispatchable;

    /**
     * @param  list<string>  $changedFields  translatable fields whose source text changed
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $productId,
        public readonly bool $created,
        public readonly array $changedFields = [],
    ) {}
}
