<?php

namespace App\Modules\Platform\Services\Dashboard;

use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\DTOs\Dashboard\OwedToPlatformData;
use App\Modules\Platform\DTOs\Dashboard\StorefrontPreviewData;
use App\Modules\Platform\DTOs\Dashboard\VendorDashboardWidgetsData;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Services\Themes\ThemePageService;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Closure;
use Throwable;

/**
 * Vendor dashboard widgets (VEN-009a / c / d / f), each read through the owning module's
 * service and only for staff holding its permission:
 *   owed           ledger receivable of the store (Billing, USD) → Money flow › Purchases
 *   opportunities  Today's chances products (Catalog) + hidden central promotions to join (Commerce)
 *   new_in         newest offered central products not in the store (Catalog, decision #65)
 *   storefront     signed theme preview per selling country (Storefront, decision #85)
 * Runs in the store's tenant context; a widget whose data fails renders its error state
 * instead of breaking the whole dashboard.
 */
class VendorDashboardWidgetService extends BaseService
{
    public const RAIL_SIZE = 4;

    /** @var list<string> */
    private array $failed = [];

    public function __construct(
        private readonly FinancePageService $finance,
        private readonly StoreCatalogService $catalog,
        private readonly VendorPromotionPageService $promotions,
        private readonly ThemePageService $themes,
    ) {}

    public function widgets(Tenant $tenant, VendorStaff $staff, ?string $previewScope = null): VendorDashboardWidgetsData
    {
        $this->failed = [];
        $can = [
            'import' => $staff->can('catalog.products.manage'),
            'flash_sale' => $staff->can('flash-sales.manage'),
            'join_sale' => $staff->can('flash-sales.manage'),
            'join_coupon' => $staff->can('coupons.manage'),
            'create_ad' => $staff->can('marketing.ads.manage') && OpenAiTools::configured(),
        ];
        $seesSales = $staff->can('flash-sales.view');
        $seesCoupons = $staff->can('coupons.view');
        $seesProducts = $staff->can('catalog.products.view');

        return new VendorDashboardWidgetsData(
            owed: $staff->can('finance.purchases.view') ? $this->guard('owed', fn (): OwedToPlatformData => $this->owed($tenant)) : null,
            opportunities: $seesProducts ? $this->guard('opportunities', fn (): array => $this->opportunities()) : null,
            offers: $seesSales || $seesCoupons ? $this->guard('opportunities', fn (): array => $this->promotions->hiddenCentralOffers($seesSales, $seesCoupons)->all()) : null,
            newIn: $seesProducts ? $this->guard('new_in', fn (): array => $this->newIn()) : null,
            storefront: $staff->can('store.themes.view') ? $this->guard('storefront', fn (): StorefrontPreviewData => $this->storefront($tenant, $staff, $previewScope)) : null,
            failed: array_values(array_unique($this->failed)),
            can: $can,
        );
    }

    public function owed(Tenant $tenant): OwedToPlatformData
    {
        $owed = $this->finance->owedToPlatform((int) $tenant->getTenantKey());

        return new OwedToPlatformData(Money::ofMinor($owed['owed']), $owed['orders'], vendor_route('finance.purchases.index'));
    }

    /**
     * Top offered products by sales (Today's chances) with the store product when added.
     *
     * @return list<array{product: Product, storeProductId: int|null}>
     */
    public function opportunities(): array
    {
        $imported = $this->catalog->importedCentralIds();

        return $this->catalog->opportunities()
            ->with('media')
            ->orderByDesc('products.sold_count')
            ->orderByDesc('products.published_at')
            ->orderByDesc('products.id')
            ->limit(self::RAIL_SIZE)
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'storeProductId' => in_array($product->id, $imported, true) ? $this->catalog->storeProductFor($product->id)?->id : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<Product>
     */
    public function newIn(): array
    {
        return $this->catalog->newProducts()
            ->with('media')
            ->orderByDesc('products.published_at')
            ->orderByDesc('products.id')
            ->limit(self::RAIL_SIZE)
            ->get()
            ->values()
            ->all();
    }

    public function storefront(Tenant $tenant, VendorStaff $staff, ?string $scope): StorefrontPreviewData
    {
        $storeUrl = StoreSettingsService::storeUrl($tenant);
        $scopes = [ThemePageService::DEFAULT_SCOPE => (string) __('platform::dashboard_vendor.storefront.all_countries')] + array_map('strval', $this->themes->countries());
        $previews = [];

        foreach (array_keys($scopes) as $key) {
            $theme = $this->themes->row((string) $key)->theme;

            if ($theme !== null) {
                $previews[(string) $key] = vendor_route('store.themes.preview', array_filter([
                    'theme' => $theme->id,
                    'country' => $key === ThemePageService::DEFAULT_SCOPE ? null : $key,
                ]));
            }
        }

        $links = [];

        foreach ([
            ['store.themes.view', 'store.themes.index', [], 'grid', 'template'],
            ['store.appearance.view', 'store.appearance', ['tab' => 'colors'], 'palette', 'colors'],
            ['store.banners.view', 'store.banners.index', [], 'image', 'banners'],
            ['store.themes.view', 'store.home-variants', [], 'home', 'homepage'],
            ['store.appearance.view', 'store.appearance', ['tab' => 'logo'], 'sparkles', 'logo'],
        ] as [$permission, $route, $query, $icon, $key]) {
            if ($staff->can($permission)) {
                $links[] = ['label' => (string) __('platform::dashboard_vendor.storefront.links.'.$key), 'icon' => $icon, 'url' => vendor_route($route, $query)];
            }
        }

        return new StorefrontPreviewData(
            storeUrl: $storeUrl,
            storeHost: (string) parse_url($storeUrl, PHP_URL_HOST),
            scopes: array_combine(array_map('strval', array_keys($scopes)), array_values($scopes)),
            previewUrls: $previews,
            currentScope: $this->themes->scopeOrDefault($scope),
            links: $links,
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $load
     * @return T|null
     */
    private function guard(string $widget, Closure $load): mixed
    {
        try {
            return $load();
        } catch (Throwable $exception) {
            report($exception);
            $this->failed[] = $widget;

            return null;
        }
    }
}
