<?php

namespace App\Modules\Ai\Services\ImageSearch;

use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Repositories\Contracts\ImageEmbeddingRepositoryContract;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;

/**
 * Storefront / mobile "search by image" (SF-ECO-012, SF-ELO-013, SF-SQF-013): the
 * shopper's photo is embedded like the catalogue images (ImageEmbedder) and compared by
 * cosine similarity with the vectors of the products THIS store sells only — central
 * products through the central index (never copied into the store, decision #65), own
 * products through the store's index. Metered by the plan's monthly `image_searches`.
 * The photo itself is never stored.
 */
class ImageSearchService
{
    public const MIN_SCORE = 0.35;

    public const LIMIT = 24;

    public function __construct(
        private readonly ImageEmbeddingRepositoryContract $embeddings,
        private readonly StoreProductRepositoryContract $storeProducts,
        private readonly ImageEmbedder $embedder,
        private readonly OpenAiTools $tools,
    ) {}

    /**
     * Ranked matches among $storeProductIds (the products the storefront may show):
     * store product id => match score 0..1, best first.
     *
     * @param  list<int>  $storeProductIds
     * @return array<int, float>
     *
     * @throws AiQuotaExceededException|AiRequestException
     */
    public function search(string $binary, string $mime, array $storeProductIds, int $tenantId): array
    {
        $query = $this->tools->run(
            new AiUsageContext(AiFeature::ImageSearch, $tenantId, 'image_search'),
            fn (OpenAiCompatibleClient $client): array => $this->embedder->embed($client, $binary, $mime)['vector'],
        );

        return $this->rank($query, $storeProductIds);
    }

    /**
     * Score every candidate (best image per product) and keep the best LIMIT above MIN_SCORE.
     *
     * @param  list<float>  $query
     * @param  list<int>  $storeProductIds
     * @return array<int, float>
     */
    public function rank(array $query, array $storeProductIds): array
    {
        if ($storeProductIds === []) {
            return [];
        }

        $byCentral = [];
        $own = [];

        foreach ($this->storeProducts->query()->whereKey($storeProductIds)->get(['id', 'source', 'central_product_id']) as $row) {
            if ($row->source === StoreProductSource::Central && $row->central_product_id !== null) {
                $byCentral[(int) $row->central_product_id][] = (int) $row->id;
            } else {
                $own[] = (int) $row->id;
            }
        }

        $scores = [];
        $keep = static function (int $storeProductId, float $score) use (&$scores): void {
            if ($score > ($scores[$storeProductId] ?? -1.0)) {
                $scores[$storeProductId] = $score;
            }
        };

        $this->embeddings->eachVector(true, array_keys($byCentral), static function (int $centralId, array $vector) use ($query, $byCentral, $keep): void {
            $score = ImageEmbedder::cosine($query, $vector);

            foreach ($byCentral[$centralId] ?? [] as $storeProductId) {
                $keep($storeProductId, $score);
            }
        });
        $this->embeddings->eachVector(false, $own, static fn (int $id, array $vector) => $keep($id, ImageEmbedder::cosine($query, $vector)));

        $scores = array_filter($scores, static fn (float $score): bool => $score >= self::MIN_SCORE);
        arsort($scores);

        return array_slice($scores, 0, self::LIMIT, true);
    }
}
