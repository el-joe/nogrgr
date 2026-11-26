<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Http\Controllers\StoreApi\Concerns\PreparesCatalogContext;
use App\Modules\Storefront\Http\Requests\StoreApi\HomeTabRequest;
use App\Modules\Storefront\Http\Resources\StoreApi\CatalogCards;
use App\Modules\Storefront\Http\Resources\StoreApi\CategoryResource;
use App\Modules\Storefront\Http\Resources\StoreApi\ListingPresenter;
use App\Modules\Storefront\Services\Catalog\HomeService;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API home (APP-005): the same sections as the store's theme home (HomeService),
 * per theme family — Ecommet sections for every store, plus the Elora / Souqify extras
 * (category tiles, rails, recommended rows, badge shelves) for those families.
 */
class HomeController extends Controller
{
    use PreparesCatalogContext;

    public function __construct(
        private readonly HomeService $home,
        private readonly StorefrontContext $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->prepareCatalog($request);
        $theme = $this->context->theme();
        $sections = $this->home->sections();
        $listing = static fn ($result, ListingKind $kind): array => ListingPresenter::present($result, new ListingQuery(kind: $kind, sort: $kind->defaultSort(), perPage: HomeService::GRID), $request);

        $data = [
            'theme' => $theme->toArray(),
            'country' => ($country = $this->context->country()) !== null ? ['id' => $country->id, 'iso2' => $country->iso2, 'name' => (string) $country->name] : null,
            'currency' => $this->context->currencyCode(),
            'sections' => [
                'hero' => array_map(static fn (BannerData $banner): array => $banner->toArray(), $sections['hero']),
                'promo' => array_map(static fn (BannerData $banner): array => $banner->toArray(), $sections['promo']),
                'deals' => CatalogCards::present($sections['deals'], $request),
                'clearance' => CatalogCards::present($sections['clearance'], $request),
                'tabs' => $sections['tabs'],
                'interests' => $listing($sections['interests'], ListingKind::NewIn),
                'flash_sale' => self::flashSale($sections['flash']),
                'best_selling' => $listing($sections['best'], ListingKind::BestSelling),
                'top_rated' => $listing($sections['rated'], ListingKind::TopRated),
                'new_in' => $listing($sections['newest'], ListingKind::NewIn),
            ],
        ];

        if (in_array($theme->family, [ThemeFamily::Elora, ThemeFamily::Souqify], true)) {
            $extra = $this->home->elora();
            $data['sections'] += [
                'categories' => array_map(static fn (Category $category): array => (new CategoryResource($category))->toArray($request), $extra['categories']),
                'trending' => CatalogCards::present($extra['trending'], $request),
                'top_rated_rail' => CatalogCards::present($extra['topRated'], $request),
                'new_rail' => CatalogCards::present($extra['newRail'], $request),
                'offers' => CatalogCards::present($extra['offers'], $request),
                'recommended' => array_map(static fn (array $row): array => CatalogCards::present($row, $request), $extra['recommended']),
                'badges' => array_map(static fn (array $shelf): array => ['id' => $shelf['id'], 'name' => $shelf['name'], 'items' => CatalogCards::present($shelf['cards'], $request)], $extra['badges']),
            ];
        }

        return JsonResponder::success('', $data);
    }

    /**
     * One page of a home tab ("recommended" or a top-level category slug).
     */
    public function tab(HomeTabRequest $request, string $tab): JsonResponse
    {
        $this->prepareCatalog($request);
        $page = (int) ($request->validated('page') ?? 1);
        $result = $this->home->tab($tab, $page);

        if ($result === null) {
            return JsonResponder::error((string) __('api::store.not_found'), 404);
        }

        return JsonResponder::success('', ListingPresenter::present($result, new ListingQuery(kind: ListingKind::Category, sort: ListingKind::Category->defaultSort(), page: $page, perPage: HomeService::GRID), $request));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function flashSale(?PromotionFlashSale $sale): ?array
    {
        return $sale === null ? null : [
            'id' => $sale->id,
            'title' => $sale->title,
            'percentage' => $sale->percentage,
            'starts_at' => $sale->startsAt->toIso8601String(),
            'ends_at' => $sale->endsAt->toIso8601String(),
            'banner_url' => $sale->bannerUrl,
        ];
    }
}
