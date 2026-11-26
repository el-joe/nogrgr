<?php

namespace App\Modules\Ai\Console;

use App\Modules\Ai\Jobs\IndexProductImages;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Backfill of the image search index: queues IndexProductImages for every central
 * product and every store-own product of every (or the given) store. Unchanged images
 * are skipped by the job, so re-running it is cheap; --force re-embeds everything.
 */
class IndexProductImagesCommand extends Command
{
    protected $signature = 'ai:index-product-images
        {--central-only : Only central catalogue products}
        {--tenant=* : Only these store ids (own products)}
        {--force : Re-embed images that are already indexed}';

    protected $description = 'Queue image search embeddings for central and store-own product images';

    public function handle(): int
    {
        if (! OpenAiTools::configured()) {
            $this->error((string) __('ai::tools.errors.openai_missing'));

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $queued = 0;

        if ($this->option('tenant') === []) {
            Product::query()->select('id')->orderBy('id')->chunkById(500, function ($products) use ($force, &$queued): void {
                foreach ($products as $product) {
                    IndexProductImages::dispatch(true, (int) $product->id, $force);
                    $queued++;
                }
            });
        }

        if (! $this->option('central-only')) {
            $tenants = Tenant::query()->when($this->option('tenant') !== [], fn ($query) => $query->whereKey(array_map('intval', (array) $this->option('tenant'))))->get();

            foreach ($tenants as $tenant) {
                $tenant->run(static function () use ($force, &$queued): void {
                    StoreProduct::query()->where('source', StoreProductSource::Own->value)->select('id')->orderBy('id')->chunkById(500, static function ($products) use ($force, &$queued): void {
                        foreach ($products as $product) {
                            IndexProductImages::dispatch(false, (int) $product->id, $force);
                            $queued++;
                        }
                    });
                });
            }
        }

        $this->info('Queued '.$queued.' product(s) for image indexing.');

        return self::SUCCESS;
    }
}
