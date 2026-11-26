<?php

namespace App\Modules\Ai\Repositories\Contracts;

use App\Modules\Ai\Models\ProductImageEmbedding;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Closure;
use Illuminate\Support\Collection;

/**
 * Image search vectors: central product images (central DB, keyed by central product
 * id) and store-own product images (tenant DB, keyed by store product id). The base
 * model is the central one; `$central = false` switches to the tenant table.
 *
 * @extends RepositoryContract<ProductImageEmbedding>
 */
interface ImageEmbeddingRepositoryContract extends RepositoryContract
{
    /**
     * Indexed rows of one product: path => source_hash.
     *
     * @return array<string, string>
     */
    public function hashes(bool $central, int $productId): array;

    /**
     * @param  array{source_hash: string, description: string, embedding: list<float>, dims: int, model: string}  $data
     */
    public function put(bool $central, int $productId, string $path, array $data): void;

    /**
     * Drop the product's rows whose path is not in $keep.
     *
     * @param  list<string>  $keep
     */
    public function prune(bool $central, int $productId, array $keep): int;

    /**
     * Stream the vectors of the given products: $each(productId, vector).
     *
     * @param  list<int>  $productIds
     * @param  Closure(int, list<float>): void  $each
     */
    public function eachVector(bool $central, array $productIds, Closure $each): void;

    /**
     * Number of indexed images per product id.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, int>
     */
    public function counts(bool $central, array $productIds): Collection;
}
