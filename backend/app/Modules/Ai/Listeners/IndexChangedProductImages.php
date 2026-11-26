<?php

namespace App\Modules\Ai\Listeners;

use App\Modules\Ai\Jobs\IndexProductImages;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Events\Store\OwnProductSaved;

/**
 * On-save image search indexing: a saved central product or store-own product queues
 * IndexProductImages (which skips unchanged images). Nothing is queued while OpenAI is
 * not configured — `ai:index-product-images` backfills later.
 */
class IndexChangedProductImages
{
    public function handle(ProductCreated|ProductUpdated|OwnProductSaved $event): void
    {
        if (! OpenAiTools::configured()) {
            return;
        }

        IndexProductImages::dispatch(! $event instanceof OwnProductSaved, $event->productId);
    }
}
