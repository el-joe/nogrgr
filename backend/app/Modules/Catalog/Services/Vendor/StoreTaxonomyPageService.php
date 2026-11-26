<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryRow;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Support\StoreCategoryRef;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * View models of the store taxonomy pages (VEN-017 / 017a / 017c / 018): product cards,
 * sortable rows, badge tabs and the product picker. Reads only.
 */
class StoreTaxonomyPageService
{
    public const CATEGORIES_PER_PAGE = 8;

    public const PRODUCTS_PER_PAGE = 12;

    public const PREVIEW_CARDS = 3;

    public function __construct(
        private readonly StoreTaxonomyReader $reader,
        private readonly StoreCatalogReader $catalog,
        private readonly StoreProductPricingService $pricing,
        private readonly StoreProductRepositoryContract $products,
        private readonly ProductBadgeRepositoryContract $badges,
    ) {}

    /**
     * VEN-017: categories page by page, each with its first product cards.
     *
     * @return LengthAwarePaginator<int, array{row: StoreCategoryRow, cards: list<array<string, mixed>>}>
     */
    public function categoriesPage(int $page, string $path): LengthAwarePaginator
    {
        $rows = $this->reader->categories();
        $slice = array_slice($rows, ($page - 1) * self::CATEGORIES_PER_PAGE, self::CATEGORIES_PER_PAGE);
        $items = array_map(fn (StoreCategoryRow $row): array => ['row' => $row, 'cards' => $this->cards(array_slice($row->productIds, 0, self::PREVIEW_CARDS))], $slice);

        return new LengthAwarePaginator($items, count($rows), self::CATEGORIES_PER_PAGE, $page, ['path' => $path, 'pageName' => 'page']);
    }

    public function category(StoreCategoryRef $ref): StoreCategoryRow
    {
        return $this->reader->find($ref) ?? abort(404);
    }

    /**
     * VEN-017a: the category's product cards, page by page (category order).
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function categoryProducts(StoreCategoryRow $row, int $page, string $path): LengthAwarePaginator
    {
        $slice = array_slice($row->productIds, ($page - 1) * self::PRODUCTS_PER_PAGE, self::PRODUCTS_PER_PAGE);

        return new LengthAwarePaginator($this->cards($slice), count($row->productIds), self::PRODUCTS_PER_PAGE, $page, ['path' => $path, 'pageName' => 'page']);
    }

    /**
     * Sortable rows (drag lists) for the given store product ids, in that order.
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, view: StoreProductView, price: string}>
     */
    public function sortRows(array $ids): array
    {
        $products = $this->products->findManyKeyed($ids);
        $this->catalog->load($products);
        $rows = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if ($product instanceof StoreProduct) {
                $rows[] = ['id' => $id, 'view' => $this->catalog->view($product), 'price' => $this->pricing->fromPrice($product)?->format() ?? '—'];
            }
        }

        return $rows;
    }

    /**
     * VEN-018 tabs: active central badges with the store's product counts.
     *
     * @return list<array{badge: ProductBadge, count: int}>
     */
    public function badgeTabs(): array
    {
        $counts = $this->reader->badgeCounts();

        return array_values($this->badges->allOrdered()
            ->filter(static fn (ProductBadge $badge): bool => $badge->is_active)
            ->map(static fn (ProductBadge $badge): array => ['badge' => $badge, 'count' => $counts[$badge->id] ?? 0])
            ->all());
    }

    /**
     * Category choices for "Add category" on a badge: ref key => "path (count)".
     *
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        $options = [];

        foreach ($this->reader->categories() as $row) {
            if ($row->productCount() > 0) {
                $options[$row->ref->key()] = $row->path.' ('.number_format($row->productCount()).')';
            }
        }

        return $options;
    }

    /**
     * Select2 source of the product pickers: store products (imported + own) by name / sku.
     *
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function searchProducts(?string $term, int $page): array
    {
        $query = $this->products->query()->orderBy('sort_order')->orderBy('id');

        if ($term !== null && trim($term) !== '') {
            $this->products->whereMatches($query, trim($term));
        }

        $results = $this->catalog->withRelations($query)->paginate(20, ['*'], 'page', max(1, $page));

        return [
            'results' => array_map(static fn (StoreProductView $view): array => ['id' => $view->id(), 'text' => $view->name.($view->sku ? ' · '.$view->sku : '')], $this->catalog->views($results->getCollection())),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    /**
     * Card payloads (imported → products._card, own → own._card), in the given order.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function cards(array $ids): array
    {
        $products = $this->products->findManyKeyed($ids);
        $this->catalog->load($products);
        $cards = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (! $product instanceof StoreProduct) {
                continue;
            }

            $cards[] = $product->isOwn()
                ? ['own' => true, 'product' => $product, 'price' => $this->pricing->ownPrice($product)]
                : ['own' => false, 'product' => $product, 'view' => $this->catalog->view($product), 'cheapest' => $this->pricing->cheapestRow($product)];
        }

        return $cards;
    }
}
