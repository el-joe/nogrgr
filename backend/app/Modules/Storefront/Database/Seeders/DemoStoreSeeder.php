<?php

namespace App\Modules\Storefront\Database\Seeders;

use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Services\Reviews\ReviewService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Services\Banners\BannerImageStore;
use App\Modules\Storefront\Services\Pages\StorePageService;
use App\Shared\Money\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fills a QA store with a browsable Ecommet demo (P7): central categories + 16 central
 * products (variants, prices, stock, weights, photos from resources/images/storefront-demo)
 * imported into the store, central + store banners, a flash sale, a coupon (DEMO10), a
 * shipping rule for the store's countries, CMS pages and customers with delivered orders
 * and reviews. Idempotent: central rows are keyed by "demo-" slugs / codes.
 *
 *   php artisan storefront:demo-store {store slug}
 *   php artisan db:seed --class="App\Modules\Storefront\Database\Seeders\DemoStoreSeeder"  (DEMO_STORE=slug, else the first active store)
 */
class DemoStoreSeeder extends Seeder
{
    public const IMAGE_DIRECTORY = 'resources/images/storefront-demo';

    public const COUPON_CODE = 'DEMO10';

    /**
     * slug => [en, ar, category, price USD, weight g, stock, variants [en, ar, extra USD]]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6?: list<array{0: string, 1: string, 2: string}>}>
     */
    private const PRODUCTS = [
        'oven' => ['Built-in Electric Oven 70 L', 'فرن كهربائي مدمج 70 لتر', 'home-appliances', '389.00', 32000, 8],
        'microwave' => ['Countertop Microwave 25 L', 'ميكروويف 25 لتر', 'home-appliances', '119.00', 12500, 15],
        'air-fryer' => ['Digital Air Fryer 5.5 L', 'قلاية هوائية رقمية 5.5 لتر', 'home-appliances', '89.00', 5200, 24],
        'blender' => ['Glass Jar Blender 1.5 L', 'خلاط بإبريق زجاجي 1.5 لتر', 'home-appliances', '59.00', 3400, 30],
        'coffee-machine' => ['Espresso Coffee Machine', 'ماكينة قهوة إسبريسو', 'home-appliances', '249.00', 7800, 9],
        'tablet' => ['11-inch Tablet', 'جهاز لوحي 11 بوصة', 'electronics', '329.00', 480, 20, [['64 GB', '64 جيجابايت', '0'], ['128 GB', '128 جيجابايت', '60.00'], ['256 GB', '256 جيجابايت', '120.00']]],
        'laptop' => ['14-inch Ultrabook Laptop', 'لابتوب ألترابوك 14 بوصة', 'electronics', '899.00', 1350, 7, [['16 GB RAM', 'ذاكرة 16 جيجابايت', '0'], ['32 GB RAM', 'ذاكرة 32 جيجابايت', '150.00']]],
        'smartphone' => ['6.5-inch Smartphone', 'هاتف ذكي 6.5 بوصة', 'electronics', '449.00', 210, 25, [['128 GB', '128 جيجابايت', '0'], ['256 GB', '256 جيجابايت', '80.00']]],
        'tv' => ['55-inch 4K Smart TV', 'تلفاز ذكي 55 بوصة 4K', 'electronics', '599.00', 16500, 6],
        'headphones' => ['Wireless Over-ear Headphones', 'سماعات رأس لاسلكية', 'electronics', '149.00', 280, 40],
        'earbuds' => ['True Wireless Earbuds', 'سماعات أذن لاسلكية', 'electronics', '79.00', 60, 50],
        'smartwatch' => ['Fitness Smartwatch', 'ساعة ذكية للياقة', 'electronics', '129.00', 45, 35],
        'speaker' => ['Portable Bluetooth Speaker', 'سماعة بلوتوث محمولة', 'electronics', '69.00', 540, 28],
        'camera' => ['Mirrorless Camera with Lens', 'كاميرا بدون مرآة مع عدسة', 'electronics', '749.00', 690, 5],
        'game-controller' => ['Wireless Game Controller', 'يد تحكم ألعاب لاسلكية', 'gaming', '59.00', 270, 45],
        'game-console' => ['Home Game Console 1 TB', 'جهاز ألعاب منزلي 1 تيرابايت', 'gaming', '499.00', 4200, 10],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const CATEGORIES = [
        'electronics' => ['Electronics', 'الإلكترونيات'],
        'home-appliances' => ['Home appliances', 'الأجهزة المنزلية'],
        'gaming' => ['Gaming', 'الألعاب'],
    ];

    private ?Tenant $tenant = null;

    public function forTenant(Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function run(): void
    {
        $tenant = $this->tenant ?? $this->defaultTenant();
        $countryIds = array_values(array_map('intval', $tenant->selling_country_ids ?? array_filter([$tenant->default_country_id])));

        $products = $this->centralProducts();
        $this->centralBanners();
        $this->flashSale($products);
        $this->coupon();
        $this->shipping($countryIds);

        $tenant->run(function () use ($tenant, $products): void {
            app(StoreCatalogService::class)->import(array_values(array_map(static fn (Product $product): int => $product->id, $products)));
            app(StorePageService::class)->ensureSystemPages((string) $tenant->name);
            $this->storeBanner();
            $this->customersWithReviews($products);
        });

        $this->command?->info("Demo data ready in store {$tenant->slug}: ".count($products).' products.');
    }

    private function defaultTenant(): Tenant
    {
        $slug = getenv('DEMO_STORE');
        $tenant = Tenant::query()
            ->when(is_string($slug) && $slug !== '', fn ($query) => $query->where('slug', $slug))
            ->where('status', TenantStatus::Active->value)
            ->orderBy('id')
            ->first();

        return $tenant instanceof Tenant ? $tenant : throw new RuntimeException('No active store to fill with demo data.');
    }

    /**
     * @return array<string, Product>
     */
    private function centralProducts(): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $key => [$en, $ar]) {
            $categories[$key] = Category::query()->where('slug', 'demo-'.$key)->first()
                ?? Category::factory()->featured()->create(['slug' => 'demo-'.$key, 'name' => ['en' => $en, 'ar' => $ar]]);
        }

        $products = [];
        $position = 0;

        foreach (self::PRODUCTS as $key => $row) {
            [$en, $ar, $category, $price, $weight, $stock] = $row;
            $slug = 'demo-'.$key;
            $product = Product::query()->where('slug', $slug)->first();

            if (! $product instanceof Product) {
                $product = Product::factory()->inCategory($categories[$category], $position)->create([
                    'slug' => $slug,
                    'sku' => 'DEMO-'.Str::upper($key),
                    'name' => ['en' => $en, 'ar' => $ar],
                    'base_price' => Money::of($price),
                    'weight_grams' => $weight,
                    'stock' => $stock,
                    'sold_count' => (16 - $position) * 7,
                ]);

                foreach ($row[6] ?? [] as $index => [$variantEn, $variantAr, $extra]) {
                    ProductVariant::query()->create([
                        'product_id' => $product->id,
                        'sku' => 'DEMO-'.Str::upper($key).'-'.($index + 1),
                        'title' => ['en' => $variantEn, 'ar' => $variantAr],
                        'price' => Money::of($price)->add(Money::of($extra)),
                        'weight_grams' => $weight,
                        'stock' => max(1, intdiv($stock, 2)),
                        'position' => $index,
                        'status' => 'active',
                    ]);
                }

                $this->attachImage($product, $key);
            }

            $products[$key] = $product;
            $position++;
        }

        return $products;
    }

    private function attachImage(Product $product, string $key): void
    {
        $source = base_path(self::IMAGE_DIRECTORY.'/product-'.$key.'.webp');

        if (! is_file($source)) {
            return;
        }

        $path = 'catalog/demo/product-'.$key.'.webp';
        $disk = Storage::disk('public');
        $disk->put($path, (string) file_get_contents($source));
        $avif = base_path(self::IMAGE_DIRECTORY.'/product-'.$key.'.avif');
        $avifPath = null;

        if (is_file($avif)) {
            $avifPath = 'catalog/demo/product-'.$key.'.avif';
            $disk->put($avifPath, (string) file_get_contents($avif));
        }

        $size = @getimagesize($source);
        $product->media()->create([
            'disk' => 'public',
            'path' => $path,
            'webp_path' => $path,
            'avif_path' => $avifPath,
            'mime_type' => 'image/webp',
            'size' => (int) filesize($source),
            'width' => is_array($size) ? $size[0] : null,
            'height' => is_array($size) ? $size[1] : null,
            'conversion_status' => $avifPath !== null ? MediaConversionStatus::Done : MediaConversionStatus::Pending,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    private function centralBanners(): void
    {
        $banners = [
            'banner-hero-electronics' => [['en' => 'Electronics week — up to 30% off', 'ar' => 'أسبوع الإلكترونيات — خصم حتى 30%'], ['en' => 'Tablets, headphones and smartwatches', 'ar' => 'أجهزة لوحية وسماعات وساعات ذكية'], '/category/demo-electronics'],
            'banner-hero-home-appliances' => [['en' => 'Upgrade your kitchen', 'ar' => 'جدّد مطبخك'], ['en' => 'Ovens, blenders and coffee machines', 'ar' => 'أفران وخلاطات وماكينات قهوة'], '/category/demo-home-appliances'],
        ];
        $images = app(BannerImageStore::class);
        $sort = 1;

        foreach ($banners as $file => [$title, $subtitle, $link]) {
            if (Banner::query()->where('image_path', BannerImageStore::DIRECTORY.'/'.$file.'.webp')->exists()) {
                $sort++;

                continue;
            }

            $image = $images->storeLocal(base_path(self::IMAGE_DIRECTORY.'/'.$file.'.webp'));
            $mobile = $images->storeLocal(base_path(self::IMAGE_DIRECTORY.'/'.$file.'-mobile.webp'));

            Banner::query()->create([
                'placement' => BannerPlacement::HomeHero,
                'title' => $title,
                'subtitle' => $subtitle,
                'cta_label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                'image_path' => $image['path'],
                'image_width' => $image['width'],
                'image_height' => $image['height'],
                'mobile_image_path' => $mobile['path'],
                'link_url' => $link,
                'sort_order' => $sort++,
                'is_active' => true,
                'store_scope' => PromotionScope::AllStores,
            ]);
        }
    }

    private function storeBanner(): void
    {
        $file = 'banner-hero-gaming';

        if (StoreBanner::query()->where('image_path', BannerImageStore::DIRECTORY.'/'.$file.'.webp')->exists()) {
            return;
        }

        $images = app(BannerImageStore::class);
        $image = $images->storeLocal(base_path(self::IMAGE_DIRECTORY.'/'.$file.'.webp'));
        $mobile = $images->storeLocal(base_path(self::IMAGE_DIRECTORY.'/'.$file.'-mobile.webp'));

        StoreBanner::query()->create([
            'placement' => BannerPlacement::HomeHero,
            'title' => ['en' => 'Level up your game night', 'ar' => 'ارتقِ بسهرة الألعاب'],
            'subtitle' => ['en' => 'Consoles and controllers in stock', 'ar' => 'أجهزة ألعاب ويد تحكم متوفرة'],
            'cta_label' => ['en' => 'Shop gaming', 'ar' => 'تسوق الألعاب'],
            'image_path' => $image['path'],
            'image_width' => $image['width'],
            'image_height' => $image['height'],
            'mobile_image_path' => $mobile['path'],
            'link_url' => '/category/demo-gaming',
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function flashSale(array $products): void
    {
        if (FlashSale::query()->where('title->en', 'Demo flash sale')->exists()) {
            return;
        }

        $sale = FlashSale::factory()->create([
            'title' => ['en' => 'Demo flash sale', 'ar' => 'عرض سريع تجريبي'],
            'discount_percentage' => 20,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
        ]);
        $sale->products()->sync([$products['headphones']->id, $products['air-fryer']->id, $products['game-controller']->id, $products['smartwatch']->id]);
    }

    private function coupon(): void
    {
        if (Coupon::query()->where('code', self::COUPON_CODE)->exists()) {
            return;
        }

        Coupon::factory()->create(['code' => self::COUPON_CODE, 'name' => 'Demo 10% off', 'percentage' => 10, 'ends_at' => now()->addMonths(3)]);
    }

    /**
     * @param  list<int>  $countryIds
     */
    private function shipping(array $countryIds): void
    {
        $missing = array_values(array_filter($countryIds, static fn (int $id): bool => ShippingRule::query()->whereHas('countries', static fn ($query) => $query->whereKey($id))->doesntExist()));

        if ($missing === []) {
            return;
        }

        $rule = ShippingRule::factory()->create(['name' => 'Demo standard shipping']);
        $rule->countries()->attach($missing);
        $zone = ShippingZone::query()->create(['shipping_rule_id' => $rule->id, 'name' => 'Demo all cities']);
        $zone->bands()->create(['name' => 'Up to 5 kg', 'min_weight_grams' => 0, 'max_weight_grams' => 5000, 'price' => Money::of(5)]);
        $zone->bands()->create(['name' => 'Heavy', 'min_weight_grams' => 5001, 'max_weight_grams' => null, 'price' => Money::of(15)]);
    }

    /**
     * Three customers, each with a delivered order of a few products and a review per line.
     *
     * @param  array<string, Product>  $products
     */
    private function customersWithReviews(array $products): void
    {
        if (ProductReview::query()->exists()) {
            return;
        }

        $customers = [
            ['Sara Ahmed', 'sara.demo@example.com', ['headphones' => [5, 'Amazing sound', 'Rich bass and the battery easily lasts a week of commuting.'], 'air-fryer' => [4, 'Crispy every time', 'Quick to heat up. The basket could be a little bigger.'], 'tablet' => [5, 'Great for reading', 'Bright screen and very smooth.']]],
            ['Omar Khaled', 'omar.demo@example.com', ['game-controller' => [5, 'Perfect grip', 'Feels solid and pairs instantly.'], 'headphones' => [4, 'Comfortable', 'I wear them for hours without discomfort.'], 'tv' => [5, 'Stunning picture', 'Colors are vivid and setup took five minutes.']]],
            ['Lina Hassan', 'lina.demo@example.com', ['coffee-machine' => [5, 'Café at home', 'Excellent crema, easy to clean.'], 'blender' => [3, 'Does the job', 'Works well, a bit loud on the highest speed.'], 'smartwatch' => [4, 'Good tracker', 'Accurate steps and sleep tracking.']]],
        ];

        foreach ($customers as $index => [$name, $email, $lines]) {
            $customer = Customer::query()->where('email', $email)->first() ?? Customer::factory()->create(['name' => $name, 'email' => $email]);
            $order = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'number' => 'DEMO-'.($index + 1).'-'.Str::upper(Str::random(4)),
                'checkout_group' => (string) Str::uuid(),
                'kind' => OrderKind::Catalog,
                'status' => OrderStatus::Delivered,
                'payment_status' => PaymentStatus::Paid,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'currency' => 'USD',
                'totals' => [],
                'placed_at' => now()->subDays(12 - $index),
                'paid_at' => now()->subDays(12 - $index),
                'shipped_at' => now()->subDays(10 - $index),
                'delivered_at' => now()->subDays(7 - $index),
            ]);
            $total = 0;

            foreach ($lines as $key => [$rating, $title, $comment]) {
                $central = $products[$key];
                $storeProduct = StoreProduct::query()->where('central_product_id', $central->id)->firstOrFail();
                $price = $central->base_price->minor;
                $total += $price;
                $item = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'line_key' => 'central:'.$central->id.':0',
                    'store_product_id' => $storeProduct->id,
                    'product_source' => StoreProductSource::Central,
                    'product_id' => $central->id,
                    'variant_key' => 0,
                    'name' => (string) $central->name,
                    'quantity' => 1,
                    'cost' => $price,
                    'profit_amount' => 0,
                    'price' => $price,
                    'sub_total' => $price,
                    'fixed_shipping' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'shipping_fee' => 0,
                    'grand_total' => $price,
                    'weight_grams' => (int) $central->weight_grams,
                ]);

                ProductReview::query()->forceCreate([
                    'store_product_id' => $storeProduct->id,
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'rating' => $rating,
                    'title' => $title,
                    'comment' => $comment,
                    'author_name' => ReviewService::authorName($name),
                    'locale' => 'en',
                    'status' => ReviewStatus::Approved,
                    'created_at' => now()->subDays(6 - $index),
                ]);
            }

            $order->forceFill(['items_grand_total' => $total, 'display_subtotal' => $total, 'grand_total' => $total, 'vendor_profit' => 0, 'platform_profit' => $total])->save();
        }
    }
}
