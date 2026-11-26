<?php

namespace App\Modules\Ai\Services\ImageSearch;

use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Repositories\Contracts\ImageEmbeddingRepositoryContract;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Keeps the image search index in step with product galleries: the first
 * MAX_IMAGES images (primary first) of a central product (central index) or of a
 * store-own product (tenant index). An image whose path / size / model did not change
 * is never sent again; removed images are pruned. Indexing is a platform cost
 * (AiFeature::ImageIndex, not metered by plan quotas).
 */
class ImageIndexService
{
    public const MAX_IMAGES = 2;

    public function __construct(
        private readonly ImageEmbeddingRepositoryContract $embeddings,
        private readonly ProductRepositoryContract $products,
        private readonly StoreProductRepositoryContract $storeProducts,
        private readonly ImageEmbedder $embedder,
        private readonly OpenAiTools $tools,
    ) {}

    /**
     * Index a central product (central DB). Returns the number of images embedded.
     */
    public function syncCentral(int $productId, bool $force = false): int
    {
        $product = $this->products->query()->withTrashed()->with('media')->find($productId);

        if ($product === null || $product->trashed()) {
            $this->embeddings->prune(true, $productId, []);

            return 0;
        }

        return $this->sync(true, $productId, $product->media, null, $force);
    }

    /**
     * Index a store-own product (tenant context). Returns the number of images embedded.
     */
    public function syncOwn(int $storeProductId, bool $force = false): int
    {
        $product = $this->storeProducts->query()->with('media')->find($storeProductId);

        if ($product === null || $product->source !== StoreProductSource::Own) {
            $this->embeddings->prune(false, $storeProductId, []);

            return 0;
        }

        return $this->sync(false, $storeProductId, $product->media, (int) tenant()?->getTenantKey(), $force);
    }

    /**
     * @param  Collection<int, CatalogMedia>  $media
     */
    private function sync(bool $central, int $productId, Collection $media, ?int $tenantId, bool $force): int
    {
        $images = $media->filter(static fn (CatalogMedia $item): bool => str_starts_with((string) $item->mime_type, 'image/') || $item->mime_type === null)->take(self::MAX_IMAGES)->values();
        $model = OpenAiTools::embeddingModel().'@'.ImageEmbedder::DIMENSIONS;
        $known = $this->embeddings->hashes($central, $productId);
        $this->embeddings->prune($central, $productId, $images->pluck('path')->all());

        $todo = $images->filter(static fn (CatalogMedia $item): bool => $force || ($known[$item->path] ?? null) !== sha1($item->path.'|'.$item->size.'|'.$model))->values();

        if ($todo->isEmpty()) {
            return 0;
        }

        $context = new AiUsageContext(AiFeature::ImageIndex, $tenantId, ($central ? 'product:' : 'store_product:').$productId);

        return $this->tools->run($context, function (OpenAiCompatibleClient $client) use ($todo, $central, $productId, $model): int {
            $done = 0;

            foreach ($todo as $item) {
                try {
                    $binary = Storage::disk($item->disk)->get($item->path);
                } catch (Throwable) {
                    $binary = null;
                }

                if (! is_string($binary) || $binary === '') {
                    continue;
                }

                $result = $this->embedder->embed($client, $binary, $item->mime_type ?: 'image/jpeg');
                $this->embeddings->put($central, $productId, $item->path, [
                    'source_hash' => sha1($item->path.'|'.$item->size.'|'.$model),
                    'description' => $result['description'],
                    'embedding' => $result['vector'],
                    'dims' => count($result['vector']),
                    'model' => $model,
                ]);
                $done++;
            }

            return $done;
        });
    }
}
