<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Services\Reviews\ReviewSummaryReader;
use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Database\Seeders\DemoStoreSeeder;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use Illuminate\Support\Facades\Storage;

/*
| Demo store (P7 QA): the command fills a store with central products (photos, variants),
| imports them, banners from both sources, a flash sale, a coupon, pages and reviews —
| and running it twice adds nothing.
*/

it('fills a store with the demo catalog once', function (): void {
    Storage::fake('public');
    $sa = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('demoshop', ['selling_country_ids' => [$sa->id], 'default_country_id' => $sa->id]);

    $this->artisan('storefront:demo-store', ['store' => 'demoshop'])->assertSuccessful();
    $this->artisan('storefront:demo-store', ['store' => 'demoshop'])->assertSuccessful();
    $this->artisan('storefront:demo-store', ['store' => 'missing-store'])->assertFailed();

    expect(Product::query()->where('slug', 'like', 'demo-%')->count())->toBe(16)
        ->and(Product::query()->where('slug', 'demo-tablet')->sole()->variants()->count())->toBe(3)
        ->and(Coupon::query()->where('code', DemoStoreSeeder::COUPON_CODE)->count())->toBe(1)
        ->and(FlashSale::query()->where('title->en', 'Demo flash sale')->count())->toBe(1);
    Storage::disk('public')->assertExists('catalog/demo/product-oven.webp');

    $tenant->run(function () use ($sa): void {
        expect(StoreProduct::query()->count())->toBe(16)
            ->and(ProductReview::query()->count())->toBe(9)
            ->and(StorePage::query()->count())->toBeGreaterThan(5)
            ->and(app(ReviewSummaryReader::class)->topRatedIds(3))->toHaveCount(3);

        $banners = app(BannerCatalog::class)->forPlacement('home.hero', $sa->id, 'en');
        expect(array_column(array_map(static fn ($banner): array => $banner->toArray(), $banners), 'source'))->toBe(['central', 'central', 'vendor'])
            ->and($banners[0]->width)->toBe(1440);
    });
});
