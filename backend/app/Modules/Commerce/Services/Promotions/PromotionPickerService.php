<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Platform\Models\Tenant;

/**
 * Select2 sources of the central promotion modals: central products (flash sale product
 * picker, ADM-026a) and stores (coupon / sale store targeting).
 *
 * Result shape: {results: [{id, text}], pagination: {more}}.
 */
class PromotionPickerService
{
    public const PER_PAGE = 20;

    public function __construct(private readonly ProductRepositoryContract $products) {}

    /**
     * @return array{results: list<array{id: int, text: string, price: string, sku: string}>, pagination: array{more: bool}}
     */
    public function products(?string $term, int $page = 1): array
    {
        $results = $this->products->search($term, [], self::PER_PAGE, $page);

        return [
            'results' => $results->getCollection()->map(static fn (Product $product): array => self::productOption($product))->values()->all(),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function stores(?string $term, int $page = 1): array
    {
        $results = $this->products->searchTenants($term, $page);

        return [
            'results' => $results->getCollection()->map(static fn (Tenant $tenant): array => ['id' => (int) $tenant->id, 'text' => (string) $tenant->name])->values()->all(),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    /**
     * @return array{id: int, text: string, price: string, sku: string}
     */
    public static function productOption(Product $product): array
    {
        $price = $product->costBasis()->format();

        return [
            'id' => (int) $product->id,
            'text' => trim((string) $product->name).' · '.$product->sku.' · '.$price,
            'price' => $price,
            'sku' => (string) $product->sku,
        ];
    }
}
