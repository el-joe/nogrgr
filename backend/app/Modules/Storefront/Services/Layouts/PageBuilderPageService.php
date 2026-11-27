<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryRow;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyPageService;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Layouts\LayoutTargetData;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Shared\Services\BaseService;

/**
 * Page model of Vendor › Your store › Page builder (VEN-032): the editing targets, the
 * pickers' options (categories, badges, banners per placement, labels of picked products),
 * the product search (Select2) and the signed draft preview URL.
 */
class PageBuilderPageService extends BaseService
{
    public function __construct(
        private readonly StoreLayoutService $layouts,
        private readonly StoreLayoutPreviewService $previews,
        private readonly StoreTaxonomyReader $taxonomy,
        private readonly StoreTaxonomyPageService $taxonomyPages,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreCatalogReader $catalog,
        private readonly BannerCatalog $banners,
    ) {}

    /**
     * Target picker: builder URL => label (home scopes, then custom pages).
     *
     * @return array<string, string>
     */
    public function targets(): array
    {
        $options = [];

        foreach ($this->layouts->homeScopes() as $scope => $label) {
            $options[vendor_route('store.page-builder', $scope === 'default' ? [] : ['scope' => $scope])] = (string) __('content::page_builder.target_home', ['scope' => $label]);
        }

        foreach ($this->layouts->customPages() as $page) {
            $options[vendor_route('store.page-builder.page', ['page' => $page->id])] = (string) __('content::page_builder.target_page', ['page' => (string) ($page->title ?: $page->slug)]);
        }

        return $options;
    }

    public function currentUrl(LayoutTargetData $target): string
    {
        return $target->isHome()
            ? vendor_route('store.page-builder', $target->countryId === null ? [] : ['scope' => $target->countryId])
            : vendor_route('store.page-builder.page', ['page' => $target->page?->id]);
    }

    /**
     * Picker options of the builder fields.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public function options(array $sections): array
    {
        $picked = [];

        foreach ($sections as $section) {
            foreach ((array) ($section['settings']['products'] ?? []) as $id) {
                $picked[] = (int) $id;
            }
        }

        $bannerOptions = static function (array $list): array {
            $options = [];

            foreach ($list as $banner) {
                /** @var BannerData $banner */
                $options[$banner->source.':'.$banner->id] = ($banner->title ?: __('content::page_builder.banner_untitled', ['id' => $banner->id])).' · '.__('content::page_builder.banner_source.'.$banner->source);
            }

            return $options;
        };

        $hero = $this->banners->all(BannerPlacement::HomeHero);
        $promo = $this->banners->all(BannerPlacement::HomePromo);

        return [
            'categories' => collect($this->taxonomy->categories(true))->mapWithKeys(static fn (StoreCategoryRow $row): array => [$row->ref->key() => $row->path])->all(),
            'badges' => $this->badges->allOrdered()->filter(static fn (ProductBadge $badge): bool => $badge->is_active)->mapWithKeys(static fn (ProductBadge $badge): array => [(string) $badge->id => (string) $badge->name])->all(),
            'banners' => [
                'home.hero' => $bannerOptions($hero),
                'home.promo' => $bannerOptions($promo),
                '*' => $bannerOptions([...$hero, ...$promo]),
            ],
            'products' => $this->productLabels(array_values(array_unique($picked))),
        ];
    }

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function searchProducts(?string $term, int $page): array
    {
        return $this->taxonomyPages->searchProducts($term, $page);
    }

    /**
     * Signed storefront URL rendering the target's draft (creates the draft row on first use).
     */
    public function previewUrl(Tenant $tenant, LayoutTargetData $target): string
    {
        $layout = $this->layouts->layout($target);

        if ($layout === null || ($layout->draft === null && $layout->published === null) || $this->layouts->isStale($target)) {
            $layout = $this->layouts->saveDraft($target, $this->layouts->workingItems($target));
        }

        // In the vendor's panel language, so the preview matches what they read.
        return $this->previews->url($tenant, $layout, $target->isHome() ? '/' : '/pages/'.$target->page?->slug).'&lang='.app()->getLocale();
    }

    /**
     * id => "name · sku" of picked products, in the given order.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function productLabels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = $this->products->findManyKeyed($ids);
        $this->catalog->load($models);
        $labels = [];

        foreach ($this->catalog->views($models->values()) as $view) {
            /** @var StoreProductView $view */
            $labels[$view->id()] = $view->name.($view->sku ? ' · '.$view->sku : '');
        }

        return array_replace(array_intersect_key(array_flip($ids), $labels), $labels);
    }
}
