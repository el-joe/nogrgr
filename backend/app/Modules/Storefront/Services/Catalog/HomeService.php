<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\ShippingQuoteService;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SalesPeriod;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Support\Collection;

/**
 * Data of the Ecommet home sections (1:1002 / m 1:2656). Every section is optional: an
 * empty one is left out by the view.
 */
class HomeService
{
    public const RAIL = 12;

    public const GRID = 15;

    /** Ecommet home page size: 10 cards per grid page and per "See More", as the old Ecommet home. */
    public const ECOMMET_PAGE = 10;

    /** Ecommet lightning deals: up to 5 products per live flash sale, as the old Ecommet home. */
    public const ECOMMET_DEALS_PER_SALE = 5;

    public const TABS = 10;

    public const CATEGORIES = 22;

    public const RECOMMENDED_ROWS = 4;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly FlashSaleCatalog $flashSales,
        private readonly StorefrontContext $context,
        private readonly BadgeShelves $badges,
        private readonly ProductIndex $index,
    ) {}

    /**
     * @return array{hero: list<BannerData>, promo: list<BannerData>, deals: list<ProductCardData>, clearance: list<ProductCardData>, tabs: list<array{key: string, label: string}>, interests: ListingResult, flash: PromotionFlashSale|null, best: ListingResult, bestFallback: bool, rated: ListingResult, newest: ListingResult}
     */
    public function sections(int $perPage = self::GRID, ?int $dealsPerSale = null, ?ListingSort $interestsSort = null): array
    {
        $dealsLimit = $dealsPerSale !== null ? $dealsPerSale * max(1, $this->liveFlashSales()->count()) : self::RAIL;

        return [
            'hero' => $this->banners('home.hero'),
            'promo' => $this->banners('home.promo'),
            'deals' => $this->cards->make($this->listing->flashDeals($dealsLimit)),
            'clearance' => $this->cards->make($this->listing->clearance(self::RAIL)),
            'tabs' => $this->tabs(),
            'interests' => $this->tab('recommended', 1, $perPage, $interestsSort),
            'flash' => $this->flashSale(),
            ...$this->bestSelling(1, $perPage),
            'rated' => $this->grid(ListingKind::TopRated, 1, $perPage),
            'newest' => $this->grid(ListingKind::NewIn, 1, $perPage),
        ];
    }

    /**
     * Extra data of the Elora and Souqify home variants (Figma "Elora Variants" 1:15432 … 1:28992): top
     * categories with images, product rails (trending / top rated / new / offers), the
     * "Recommended for you" rows and the store's badge shelves (badge order, decision #79).
     *
     * @return array{categories: list<Category>, trending: list<ProductCardData>, topRated: list<ProductCardData>, newRail: list<ProductCardData>, offers: list<ProductCardData>, recommended: list<list<ProductCardData>>, badges: list<array{id: int, name: string, cards: list<ProductCardData>}>}
     */
    public function elora(): array
    {
        $recommended = $this->listing->listing(new ListingQuery(kind: ListingKind::NewIn, sort: ListingSort::Recommended, page: 1, perPage: self::RAIL * self::RECOMMENDED_ROWS));

        return [
            'categories' => array_slice(array_map(static fn (array $node): Category => $node['category'], $this->categories->tree()), 0, self::CATEGORIES),
            'trending' => $this->cards->make($this->listing->rail(ListingKind::BestSelling, self::RAIL)),
            'topRated' => $this->cards->make($this->listing->rail(ListingKind::TopRated, self::RAIL)),
            'newRail' => $this->cards->make($this->listing->rail(ListingKind::NewIn, self::RAIL)),
            'offers' => $this->cards->make($this->listing->rail(ListingKind::Offers, self::RAIL)),
            'recommended' => array_values(array_filter(array_chunk($recommended->cards, self::RAIL))),
            'badges' => $this->badges->shelves(self::RAIL),
        ];
    }

    /**
     * Extra data of the Elora v1 home (Figma 1:15432 / m 1:21125): the category circles (the
     * top-level categories first, then their sub-categories, each with its own image or else a
     * photo of one of its products), the in-stock "Fast delivery" tab, the "delivered by" date
     * of the shopping country and the free-shipping weight of the bottom strip.
     *
     * @return array{tiles: list<array{category: Category, image: string|null}>, fast: list<ProductCardData>, deliveryBy: string|null, freeShippingWeight: string|null}
     */
    public function eloraBase(): array
    {
        $countryId = $this->context->countryId();
        $threshold = $countryId !== null ? rescue(fn (): ?int => app(ShippingQuoteService::class)->freeShippingThreshold($countryId), null, false) : null;
        $date = $countryId !== null ? rescue(fn () => app(DeliveryEstimateService::class)->estimateFor($countryId)->maxDate, null, false) : null;

        return [
            'tiles' => $this->categoryTiles(),
            'fast' => $this->cards->make(array_slice(array_values(array_filter($this->listing->rail(ListingKind::BestSelling, self::RAIL * 4), static fn (IndexEntry $entry): bool => $entry->inStock)), 0, self::RAIL)),
            'deliveryBy' => $date?->locale(app()->getLocale())->translatedFormat('j F'),
            'freeShippingWeight' => $threshold !== null && $threshold > 0 ? ProductCards::weight($threshold) : null,
        ];
    }

    /**
     * @return list<array{category: Category, image: string|null}>
     */
    private function categoryTiles(): array
    {
        $queue = array_map(static fn (array $node): Category => $node['category'], $this->categories->tree());
        $categories = [];

        for ($index = 0; $index < count($queue) && count($categories) < self::CATEGORIES; $index++) {
            $categories[] = $queue[$index];
            array_push($queue, ...$this->categories->children($queue[$index]));
        }

        $entries = $this->index->entries();
        $used = [];
        $tiles = [];

        foreach ($categories as $category) {
            $image = $category->thumbnailUrl();

            if ($image === null) {
                $ids = [$category->id => true];
                $walk = [$category];

                while ($walk !== []) {
                    foreach ($this->categories->children(array_pop($walk)) as $child) {
                        $ids[$child->id] = true;
                        $walk[] = $child;
                    }
                }

                foreach ($entries as $entry) {
                    if ($entry->image !== null && ! isset($used[$entry->image]) && array_intersect_key($ids, array_flip($entry->categoryIds)) !== []) {
                        $image = $entry->image;
                        $used[$image] = true;
                        break;
                    }
                }
            }

            $tiles[] = ['category' => $category, 'image' => $image];
        }

        return $tiles;
    }

    /**
     * "Explore your interests" chips: Recommended + the store's top-level categories.
     *
     * @return list<array{key: string, label: string}>
     */
    public function tabs(): array
    {
        $tabs = [['key' => 'recommended', 'label' => __st('catalog.home.recommended')]];

        foreach (array_slice($this->categories->tree(), 0, self::TABS) as $node) {
            $tabs[] = ['key' => $node['category']->slug, 'label' => (string) $node['category']->name];
        }

        return $tabs;
    }

    /**
     * One page of a home tab (Recommended or a category slug); null for an unknown tab.
     */
    public function tab(string $key, int $page = 1, int $perPage = self::GRID, ?ListingSort $recommendedSort = null): ?ListingResult
    {
        if ($key === 'recommended') {
            return $this->listing->listing(new ListingQuery(kind: ListingKind::NewIn, sort: $recommendedSort ?? ListingSort::Recommended, page: $page, perPage: $perPage));
        }

        $category = $this->categories->findBySlug($key);

        return $category instanceof Category
            ? $this->listing->listing(new ListingQuery(kind: ListingKind::Category, sort: ListingSort::Recommended, page: $page, perPage: $perPage), $category)
            : null;
    }

    /**
     * Best-selling grid of the home page. Like the old Ecommet home, a store without sales
     * yet still shows its in-stock products here, ordered the best-selling way (sales, then
     * rating, newest); `bestFallback` tells the view to page through that same order.
     *
     * @return array{best: ListingResult, bestFallback: bool}
     */
    public function bestSelling(int $page = 1, int $perPage = self::GRID): array
    {
        // The listing scope itself falls back to in-stock products without sales (ProductListing::bestSellingScope),
        // so the home grid and the best-selling page rank and page identically.
        return ['best' => $this->grid(ListingKind::BestSelling, $page, $perPage), 'bestFallback' => false];
    }

    public function grid(ListingKind $kind, int $page = 1, int $perPage = self::GRID): ListingResult
    {
        return $this->listing->listing(new ListingQuery(kind: $kind, sort: $kind->defaultSort(), page: $page, perPage: $perPage, period: SalesPeriod::Days30));
    }

    /**
     * The live flash sale of the country that ends first (countdown band).
     */
    public function flashSale(): ?PromotionFlashSale
    {
        $countryId = $this->context->countryId();

        if ($countryId === null) {
            return null;
        }

        return $this->liveFlashSales()
            ->sortBy(static fn (PromotionFlashSale $sale): int => $sale->endsAt->getTimestamp())
            ->first();
    }

    /**
     * Live flash sales of the shopping country.
     *
     * @return Collection<int, PromotionFlashSale>
     */
    private function liveFlashSales(): Collection
    {
        $countryId = $this->context->countryId();

        if ($countryId === null) {
            return new Collection;
        }

        return $this->flashSales->all($countryId, liveOnly: true)
            ->filter(static fn (PromotionFlashSale $sale): bool => $sale->isLive())
            ->values();
    }

    /**
     * @return list<BannerData>
     */
    private function banners(string $placement): array
    {
        if (! class_exists(BannerCatalog::class)) {
            return [];
        }

        return app(BannerCatalog::class)->forPlacement($placement, $this->context->countryId(), app()->getLocale());
    }
}
