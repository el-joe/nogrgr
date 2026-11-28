<?php

namespace App\Modules\Storefront\Database\Seeders;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Services\Promotions\StoreFlashSaleService;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\SocialNetwork;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Services\Banners\BannerImageStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Demo content of the Elora v1 home (Figma 1:15432) for one store: the four hero slides
 * (2408 × 774 = the design's 1204 × 387), the flash sale artwork on a live store flash sale,
 * the FLASH30 coupon and the footer social profiles. Store (tenant) data only and idempotent;
 * existing banners are left as they are. The "Use Code" chip itself is set in the page builder
 * (flash sale section › coupon code).
 *
 *   DEMO_STORE=vendor php artisan db:seed --class="App\Modules\Storefront\Database\Seeders\EloraHomeDemoSeeder"
 */
class EloraHomeDemoSeeder extends Seeder
{
    public const IMAGE_DIRECTORY = 'resources/images/elora';

    public const COUPON_CODE = 'FLASH30';

    private const FLASH_BANNER = 'elora-flash-sale.webp';

    /** Products of a newly demoed flash sale (one desktop rail and a bit). */
    private const FLASH_PRODUCTS = 10;

    /** @var list<array{en: string, ar: string}> */
    private const SLIDES = [
        ['en' => 'Ramadan Kareem — special Ramadan offers', 'ar' => 'رمضان كريم — عروض رمضانية مميزة'],
        ['en' => 'Happy Eid al-Fitr — exclusive offers', 'ar' => 'عيد الفطر السعيد — عروض حصرية'],
        ['en' => 'Blessed Eid al-Adha — gifts for the family', 'ar' => 'عيد الأضحى المبارك — أفضل الهدايا للعائلة'],
        ['en' => 'Ramadan nights of light — warm offers', 'ar' => 'رمضان ليالي نور — أجواء دافئة وعروض مميزة'],
    ];

    private ?Tenant $tenant = null;

    public function forTenant(Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function run(): void
    {
        $slug = getenv('DEMO_STORE');
        $tenant = $this->tenant ?? Tenant::query()
            ->when(is_string($slug) && $slug !== '', fn ($query) => $query->where('slug', $slug))
            ->where('status', TenantStatus::Active->value)
            ->orderBy('id')
            ->first();

        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('No active store to fill with the Elora home demo.');
        }

        $tenant->run(function (): void {
            $this->heroSlides();
            $this->flashSale();
            $this->coupon();
            $this->socialLinks();
        });
    }

    private function heroSlides(): void
    {
        $images = app(BannerImageStore::class);

        foreach (self::SLIDES as $index => $title) {
            $file = 'elora-hero-'.($index + 1).'.webp';

            if (StoreBanner::query()->where('image_path', BannerImageStore::DIRECTORY.'/'.$file)->exists()) {
                continue;
            }

            $path = BannerImageStore::DIRECTORY.'/'.$file;
            $source = base_path(self::IMAGE_DIRECTORY.'/demo/hero-'.($index + 1).'.webp');
            $this->copy('demo/hero-'.($index + 1).'.webp', $path);
            $size = getimagesize($source) ?: [null, null];

            StoreBanner::query()->create([
                'placement' => BannerPlacement::HomeHero,
                'title' => $title,
                'image_path' => $path,
                'image_width' => $size[0],
                'image_height' => $size[1],
                'link_url' => '/offers',
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        // Drops the cached banner lists (same invalidation as the vendor banner screen).
        StoreBanner::query()->where('image_path', 'like', BannerImageStore::DIRECTORY.'/elora-hero-%')->first()?->touch();
    }

    private function copy(string $file, string $path): void
    {
        if (! Storage::disk('public')->put($path, (string) file_get_contents(base_path(self::IMAGE_DIRECTORY.'/'.$file)))) {
            throw new RuntimeException("Could not write {$path} to the store disk.");
        }
    }

    private function flashSale(): void
    {
        $banner = BannerImageStore::DIRECTORY.'/'.self::FLASH_BANNER;
        $this->copy('flash-sale.webp', $banner);
        $attributes = [
            'title' => ['en' => 'Save on electronics. Best sellers + more', 'ar' => 'وفّر على الإلكترونيات. الأكثر مبيعًا والمزيد'],
            'banner_path' => $banner,
            'is_active' => true,
        ];
        $sale = StoreFlashSale::query()->where('ends_at', '>', now())->orderBy('id')->first();

        if ($sale instanceof StoreFlashSale) {
            $sale->update($attributes + ['ends_at' => now()->addDays(30)]);
        } else {
            $sale = StoreFlashSale::query()->create($attributes + ['discount_percentage' => 10, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(30)]);
        }

        $sales = app(StoreFlashSaleService::class);

        foreach (StoreProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->limit(self::FLASH_PRODUCTS)->get() as $product) {
            $sales->attachProduct($sale, $product->isOwn() ? ProductRef::own($product->id) : ProductRef::central((int) $product->central_product_id));
        }
    }

    private function coupon(): void
    {
        if (StoreCoupon::query()->withTrashed()->where('code', self::COUPON_CODE)->exists()) {
            return;
        }

        StoreCoupon::query()->create([
            'code' => self::COUPON_CODE,
            'name' => 'Flash sale 30% off',
            'type' => CouponType::Percentage,
            'percentage' => 30,
            'min_spend_minor' => 0,
            'first_order_only' => false,
            'is_active' => true,
        ]);
    }

    private function socialLinks(): void
    {
        $order = (int) StoreSocialLink::query()->max('sort_order');

        foreach ([SocialNetwork::Facebook, SocialNetwork::X, SocialNetwork::Instagram, SocialNetwork::Youtube, SocialNetwork::Linkedin] as $network) {
            if (StoreSocialLink::query()->where('network', $network->value)->exists()) {
                continue;
            }

            StoreSocialLink::query()->create(['network' => $network, 'url' => 'https://www.'.$network->value.'.com/', 'is_active' => true, 'sort_order' => ++$order]);
        }
    }
}
