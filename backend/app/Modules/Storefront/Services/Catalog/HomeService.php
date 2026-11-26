<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SalesPeriod;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Modules\Storefront\Services\StorefrontContext;

/**
 * Data of the Ecommet home sections (1:1002 / m 1:2656). Every section is optional: an
 * empty one is left out by the view.
 */
class HomeService
{
    public const RAIL = 12;

    public const GRID = 15;

    public const TABS = 10;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly FlashSaleCatalog $flashSales,
        private readonly StorefrontContext $context,
    ) {}

    /**
     * @return array{hero: list<BannerData>, promo: list<BannerData>, deals: list<ProductCardData>, clearance: list<ProductCardData>, tabs: list<array{key: string, label: string}>, interests: ListingResult, flash: PromotionFlashSale|null, best: ListingResult, rated: ListingResult, newest: ListingResult}
     */
    public function sections(): array
    {
        return [
            'hero' => $this->banners('home.hero'),
            'promo' => $this->banners('home.promo'),
            'deals' => $this->cards->make($this->listing->flashDeals(self::RAIL)),
            'clearance' => $this->cards->make($this->listing->clearance(self::RAIL)),
            'tabs' => $this->tabs(),
            'interests' => $this->tab('recommended'),
            'flash' => $this->flashSale(),
            'best' => $this->grid(ListingKind::BestSelling),
            'rated' => $this->grid(ListingKind::TopRated),
            'newest' => $this->grid(ListingKind::NewIn),
        ];
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
    public function tab(string $key, int $page = 1): ?ListingResult
    {
        if ($key === 'recommended') {
            return $this->listing->listing(new ListingQuery(kind: ListingKind::NewIn, sort: ListingSort::Recommended, page: $page, perPage: self::GRID));
        }

        $category = $this->categories->findBySlug($key);

        return $category instanceof Category
            ? $this->listing->listing(new ListingQuery(kind: ListingKind::Category, sort: ListingSort::Recommended, page: $page, perPage: self::GRID), $category)
            : null;
    }

    public function grid(ListingKind $kind, int $page = 1): ListingResult
    {
        return $this->listing->listing(new ListingQuery(kind: $kind, sort: $kind->defaultSort(), page: $page, perPage: self::GRID, period: SalesPeriod::Days30));
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

        return $this->flashSales->all($countryId, liveOnly: true)
            ->filter(static fn (PromotionFlashSale $sale): bool => $sale->isLive())
            ->sortBy(static fn (PromotionFlashSale $sale): int => $sale->endsAt->getTimestamp())
            ->first();
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
