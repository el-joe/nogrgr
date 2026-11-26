<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Models\ProductImageEmbedding;
use App\Modules\Ai\Models\StoreImageEmbedding;
use App\Modules\Ai\Repositories\Contracts\ImageEmbeddingRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<ProductImageEmbedding>
 */
class ImageEmbeddingRepository extends BaseRepository implements ImageEmbeddingRepositoryContract
{
    protected function model(): string
    {
        return ProductImageEmbedding::class;
    }

    public function hashes(bool $central, int $productId): array
    {
        return $this->forProduct($central, $productId)->pluck('source_hash', 'path')->map(static fn (mixed $hash): string => (string) $hash)->all();
    }

    public function put(bool $central, int $productId, string $path, array $data): void
    {
        $this->table($central)->updateOrCreate([$this->key($central) => $productId, 'path' => $path], $data);
    }

    public function prune(bool $central, int $productId, array $keep): int
    {
        return $this->forProduct($central, $productId)->when($keep !== [], static fn (Builder $query) => $query->whereNotIn('path', $keep))->delete();
    }

    public function eachVector(bool $central, array $productIds, Closure $each): void
    {
        if ($productIds === []) {
            return;
        }

        $key = $this->key($central);

        foreach (array_chunk($productIds, 500) as $chunk) {
            $this->table($central)->toBase()->whereIn($key, $chunk)->select([$key, 'embedding'])->orderBy('id')->lazy(500)->each(static function (object $row) use ($each, $key): void {
                $vector = json_decode((string) $row->embedding, true);

                if (is_array($vector) && $vector !== []) {
                    $each((int) $row->{$key}, $vector);
                }
            });
        }
    }

    public function counts(bool $central, array $productIds): Collection
    {
        $key = $this->key($central);

        return $productIds === [] ? collect() : $this->table($central)->toBase()->whereIn($key, $productIds)->groupBy($key)->selectRaw($key.' as id, count(*) as n')->pluck('n', 'id')->map(static fn (mixed $n): int => (int) $n);
    }

    /**
     * @return Builder<ProductImageEmbedding>|Builder<StoreImageEmbedding>
     */
    private function table(bool $central): Builder
    {
        return $central ? $this->query() : StoreImageEmbedding::query();
    }

    /**
     * @return Builder<ProductImageEmbedding>|Builder<StoreImageEmbedding>
     */
    private function forProduct(bool $central, int $productId): Builder
    {
        return $this->table($central)->where($this->key($central), $productId);
    }

    private function key(bool $central): string
    {
        return $central ? 'product_id' : 'store_product_id';
    }
}
