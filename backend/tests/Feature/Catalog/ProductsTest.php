<?php

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\MediaType;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductOfferedToStores;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Jobs\GenerateVideoPoster;
use App\Modules\Catalog\Jobs\Requests\NotifyStoreStaff;
use App\Modules\Catalog\Listeners\NotifyStoresOfOfferedProduct;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Catalog\Services\Media\ImageVariantConverter;
use App\Modules\Catalog\Services\Media\VideoPosterGenerator;
use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Products (ADM-010 / 010b / 011, #115 old controller form): permissions, list + filters +
| trash, create / edit across every section (basics, primary image, gallery, translations,
| pricing & inventory, categories, variants, badges, tenant assignments + notifications),
| no country availability, validate-on-blur, delete / restore, CSV export and price list.
*/

function productPayload(array $overrides = []): array
{
    return array_replace([
        'name' => ['en' => 'Wireless Earbuds Pro', 'ar' => 'سماعات لاسلكية برو'],
        'summary' => ['en' => 'Noise-cancelling earbuds'],
        'description' => ['en' => 'Long description', 'ar' => 'وصف طويل'],
        'sku' => 'NG-10293',
        'slug' => '',
        'status' => 'published',
        'factory' => 'FAC-023',
        'base_price' => '79.00',
        'sale_price' => '59.00',
        'cost_price' => '24.00',
        'weight_grams' => '180',
        'is_taxable' => '1',
        'stock' => '0',
        'category_ids' => [],
        'label_ids' => [],
        'badges' => [],
        'assignment_scope' => 'all',
        'tenant_ids' => [],
        'manage_stock' => '1',
        'min_stock' => '5',
    ], $overrides);
}

function defaultBranch(): Branch
{
    $branch = Branch::factory()->create();
    DB::table('branch_default_countries')->insert(['country_id' => $branch->country_id, 'branch_id' => $branch->id]);

    return $branch;
}

function productTenant(): Tenant
{
    return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Megz Store',
        'slug' => 'store-'.Str::lower(Str::random(10)),
        'status' => TenantStatus::Active,
    ]));
}

it('redirects guests and forbids admins without product permissions', function (): void {
    $product = Product::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/products'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/catalog/products'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/products/data'))->assertForbidden();

    $this->actingAs(catalogAdmin(['catalog.products.view']), 'admin');
    $this->get($this->centralUrl('/admin/catalog/products'))->assertOk();
    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/prices"))->assertOk();
    $this->getJson($this->centralUrl('/admin/catalog/products/export'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/catalog/products/{$product->id}/prices/export"))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/products/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload())->assertForbidden();
    $this->getJson($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertForbidden();
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload())->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'sku'])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/catalog/products/{$product->id}"))->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/catalog/products/{$product->id}/restore"))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/products/tenants'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/products/sort'))->assertForbidden();
    $this->putJson($this->centralUrl('/admin/catalog/products/sort'), ['ids' => [$product->id]])->assertForbidden();
});

it('renders the list and filters the table by tab, status, category, scope and stock', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $category = Category::factory()->create();
    $inCategory = Product::factory()->inCategory($category)->create(['stock' => 30, 'min_stock' => 5]);
    Product::factory()->draft()->create(['stock' => 0]);
    Product::factory()->create(['stock' => 3, 'min_stock' => 5, 'assignment_scope' => AssignmentScope::Selected]);
    Product::factory()->create()->delete();

    $this->get($this->centralUrl('/admin/catalog/products'))->assertOk()->assertSee(__('catalog::enums.product_list_tab.trash'));

    $data = fn (string $query): array => $this->getJson($this->centralUrl('/admin/catalog/products/data?draw=1&start=0&length=10&'.$query))->assertOk()->json();

    expect($data('')['recordsTotal'])->toBe(3)
        ->and($data('tab=draft')['recordsFiltered'])->toBe(1)
        ->and($data('tab=trash')['recordsFiltered'])->toBe(1)
        ->and($data('tab=out_of_stock')['recordsFiltered'])->toBe(1)
        ->and($data('status=published')['recordsFiltered'])->toBe(2)
        ->and($data("category_id={$category->id}")['data'][0]['id'])->toBe($inCategory->id)
        ->and($data('scope=selected')['recordsFiltered'])->toBe(1)
        ->and($data('stock=low')['recordsFiltered'])->toBe(1)
        ->and($data('stock=in_stock')['recordsFiltered'])->toBe(1)
        ->and($data('search[value]='.urlencode($inCategory->sku))['recordsFiltered'])->toBe(1);
});

it('creates a product with translations, pricing in minor units and every relation', function (): void {
    Event::fake([ProductCreated::class, StockChanged::class]);
    Queue::fake();
    $this->actingAs(catalogAdmin(['catalog.products.manage']), 'admin');
    $category = Category::factory()->create();
    $label = ProductLabel::factory()->create();
    $tenant = productTenant();
    $branch = defaultBranch();

    $this->get($this->centralUrl('/admin/catalog/products/create'))->assertOk()
        ->assertSee('data-product-builder', false)
        ->assertSee('data-category-tree', false)
        ->assertSee(__('catalog::products.fields.primary_image'))
        ->assertSee(__('catalog::products.sections.tenants'))
        ->assertDontSee('country_mode', false)
        ->assertDontSee('delivery_scope', false);

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload([
        'label' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'],
        'meta_title' => ['en' => 'Earbuds SEO'],
        'meta_keywords' => ['en' => 'earbuds, audio'],
        'category_ids' => [$category->id],
        'label_ids' => [$label->id],
        'badges' => ['best_selling', 'new_in'],
        'assignment_scope' => 'selected',
        'tenant_ids' => [$tenant->id],
        'stock' => '12',
    ]))->assertCreated();

    $product = Product::query()->sole();
    expect($product->getTranslation('name', 'ar'))->toBe('سماعات لاسلكية برو')
        ->and($product->slug)->toBe('wireless-earbuds-pro')
        ->and($product->base_price->minor)->toBe(7900)
        ->and($product->sale_price?->minor)->toBe(5900)
        ->and($product->costBasis()->minor)->toBe(5900)
        ->and($product->cost_price?->minor)->toBe(2400)
        ->and($product->weight_grams)->toBe(180)
        ->and($product->factory)->toBe('FAC-023')
        ->and($product->getTranslation('label', 'ar'))->toBe('الأكثر مبيعًا')
        ->and($product->getTranslation('meta_title', 'en'))->toBe('Earbuds SEO')
        ->and($product->getTranslation('meta_keywords', 'en'))->toBe('earbuds, audio')
        ->and($product->delivery_scope->value)->toBe('all_zones')
        ->and($product->requires_shipping)->toBeTrue()
        ->and($product->categories->pluck('id')->all())->toBe([$category->id])
        ->and($product->labels->pluck('id')->all())->toBe([$label->id])
        ->and($product->assignedTenants->pluck('id')->all())->toBe([$tenant->id])
        ->and($product->stock)->toBe(12)
        ->and(BranchStock::query()->where('product_id', $product->id)->where('branch_id', $branch->id)->value('on_hand'))->toBe(12)
        ->and($product->published_at)->not->toBeNull();

    $badgeTypes = ProductBadge::query()->whereIn('id', ProductBadgeAssignment::query()->where('product_id', $product->id)->pluck('product_badge_id'))->pluck('type')->map->value->sort()->values()->all();
    expect($badgeTypes)->toBe(['best_selling', 'new_in']);

    Event::assertDispatched(ProductCreated::class, fn (ProductCreated $event): bool => $event->productId === $product->id);
    Event::assertDispatched(StockChanged::class, fn (StockChanged $event): bool => $event->totalStock === 12);
});

it('has no country availability: the form ignores country fields and keeps the shipping columns', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $product = Product::factory()->create(['sku' => 'NG-10293', 'delivery_scope' => 'digital', 'requires_shipping' => false]);

    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['country_mode' => 'selected', 'country_ids' => [$sa->id]]))->assertOk();

    $product->refresh();
    expect(DB::table('products')->where('id', $product->id)->value('allowed_country_ids'))->toBeNull()
        ->and($product->delivery_scope->value)->toBe('digital')
        ->and($product->requires_shipping)->toBeFalse();
});

it('clears old country restrictions so every product sells in every country', function (): void {
    $product = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update(['allowed_country_ids' => json_encode([1, 2])]);

    (require database_path('../app/Modules/Catalog/Database/migrations/central/2026_12_03_000100_clear_product_allowed_countries.php'))->up();

    expect(DB::table('products')->where('id', $product->id)->value('allowed_country_ids'))->toBeNull();
});

it('validates the product form', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    Product::factory()->create(['sku' => 'TAKEN']);

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload([
        'name' => ['en' => ''],
        'sku' => 'TAKEN',
        'base_price' => '10.001',
        'sale_price' => '50',
        'cost_price' => '-1',
        'stock' => '-2',
        'status' => 'live',
        'assignment_scope' => 'selected',
        'tenant_ids' => [],
        'badges' => ['unknown'],
    ]))->assertJsonValidationErrors(['name.en', 'name.ar', 'sku', 'base_price', 'sale_price', 'cost_price', 'stock', 'status', 'tenant_ids', 'badges.0']);

    // Promotional price is optional; cost price too (old controller).
    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['sale_price' => '', 'cost_price' => '']))->assertCreated();
    $product = Product::query()->where('sku', 'NG-10293')->sole();
    expect($product->base_price->minor)->toBe(7900)->and($product->sale_price)->toBeNull()->and($product->cost_price)->toBeNull();
});

it('validates one field on blur', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create(['sku' => 'TAKEN']);

    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'sku', 'sku' => 'TAKEN', 'name' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('sku')->assertJsonMissingValidationErrors('name.en');
    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'sku', 'sku' => 'FREE'])->assertNoContent();
    $this->postJson($this->centralUrl("/admin/catalog/products/validate/{$product->id}"), ['field' => 'sku', 'sku' => 'TAKEN'])->assertNoContent();
    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'name.ar', 'name' => ['ar' => '']])->assertJsonValidationErrors('name.ar');
});

it('builds variants with price, stock, SKU and weight, writes stock to the default branch and keeps other branches', function (): void {
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $size = Attribute::factory()->withOptions(['S', 'M'])->create();
    $color = Attribute::factory()->withOptions(['Black', 'White'])->create();
    [$s, $m] = $size->options->pluck('id')->all();
    [$black, $white] = $color->options->pluck('id')->all();
    $riyadh = defaultBranch();
    $jeddah = Branch::factory()->create();
    $key = fn (int ...$ids): string => collect($ids)->sort()->implode('-');

    $variants = [];
    foreach ([[$s, $black], [$s, $white], [$m, $black], [$m, $white]] as [$a, $b]) {
        $stock = match ([$a, $b]) {
            [$s, $black] => '120',
            [$m, $white] => '4',
            default => '',
        };
        $variants[$key($a, $b)] = ['option_ids' => [$a, $b], 'sku' => "NG-{$a}-{$b}", 'price' => $a === $m ? '65.00' : '', 'stock' => $stock, 'weight_grams' => '200', 'is_active' => '1'];
    }

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['variants' => $variants]))->assertCreated();

    $product = Product::query()->sole();
    $sBlack = ProductVariant::query()->where('sku', "NG-{$s}-{$black}")->sole();
    // Stock in another branch is managed under Shipping › Branches; the form keeps it.
    BranchStock::query()->create(['branch_id' => $jeddah->id, 'product_id' => $product->id, 'product_variant_id' => $sBlack->id, 'variant_key' => $sBlack->id, 'on_hand' => 80]);
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        'variants' => collect($variants)->map(fn (array $row): array => $row + ['id' => ProductVariant::query()->where('sku', $row['sku'])->value('id')])->all(),
    ]))->assertOk();
    expect($product->variants()->count())->toBe(4)
        ->and($product->attributes()->pluck('attributes.id')->sort()->values()->all())->toBe(collect([$size->id, $color->id])->sort()->values()->all())
        ->and($sBlack->refresh()->stock)->toBe(200)
        ->and($sBlack->price)->toBeNull()
        ->and(ProductVariant::query()->where('sku', "NG-{$m}-{$black}")->sole()->price?->minor)->toBe(6500)
        ->and($product->refresh()->stock)->toBe(204)
        ->and(BranchStock::query()->where('branch_id', $jeddah->id)->where('variant_key', $sBlack->id)->value('on_hand'))->toBe(80);

    // Drop size M; keep S/Black (by id) and S/White.
    $sWhite = ProductVariant::query()->where('sku', "NG-{$s}-{$white}")->sole();
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        'variants' => [
            $key($s, $black) => ['id' => $sBlack->id, 'option_ids' => [$s, $black], 'sku' => "NG-{$s}-{$black}", 'stock' => '10', 'is_active' => '1'],
            $key($s, $white) => ['id' => $sWhite->id, 'option_ids' => [$s, $white], 'sku' => "NG-{$s}-{$white}", 'is_active' => '0'],
        ],
    ]))->assertOk();

    expect($product->variants()->count())->toBe(2)
        ->and(ProductVariant::withTrashed()->where('product_id', $product->id)->onlyTrashed()->count())->toBe(2)
        ->and($sWhite->refresh()->status->value)->toBe('inactive')
        ->and(BranchStock::query()->where('product_id', $product->id)->pluck('variant_key')->unique()->sort()->values()->all())->toBe(collect([$sBlack->id, $sWhite->id])->sort()->values()->all())
        ->and($product->refresh()->stock)->toBe(90);
});

it('rejects inconsistent variant combinations', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $size = Attribute::factory()->withOptions(['S', 'M'])->create();
    $color = Attribute::factory()->withOptions(['Black'])->create();
    [$s, $m] = $size->options->pluck('id')->all();
    $black = $color->options->first()->id;

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['variants' => [
        "{$s}-{$m}" => ['option_ids' => [$s, $m]],
    ]]))->assertUnprocessable()->assertJson(['message' => __('catalog::products.variants.one_per_attribute')]);

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['variants' => [
        "{$s}" => ['option_ids' => [$s]],
        "{$m}-{$black}" => ['option_ids' => [$m, $black]],
    ]]))->assertUnprocessable()->assertJson(['message' => __('catalog::products.variants.same_attributes')]);

    expect(Product::query()->count())->toBe(0);
});

it('uploads, reorders and removes gallery images and converts them', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'images' => [UploadedFile::fake()->image('a.png', 400, 400), UploadedFile::fake()->image('b.png', 400, 400)],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $product = Product::query()->sole();
    [$first, $second] = $product->media->all();
    expect($first->is_primary)->toBeTrue()->and($first->conversion_status)->toBe(MediaConversionStatus::Pending);
    Queue::assertPushed(ConvertCatalogMedia::class, 2);

    (new ConvertCatalogMedia($first->id))->handle(app(ImageVariantConverter::class));
    expect($first->refresh()->conversion_status)->toBe(MediaConversionStatus::Done)
        ->and($first->webp_path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($first->webp_path);

    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        '_method' => 'PUT',
        'images_keep' => [$second->id],
    ]), ['Accept' => 'application/json'])->assertOk();

    expect($product->media()->pluck('id')->all())->toBe([$second->id])
        ->and($second->refresh()->is_primary)->toBeTrue();
    Storage::disk('public')->assertMissing($first->path);

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'sku' => 'NG-2',
        'images' => [UploadedFile::fake()->image('tiny.png', 50, 50)],
    ]), ['Accept' => 'application/json'])->assertJsonValidationErrors('images.0');
});

it('soft deletes and restores products', function (): void {
    Event::fake([ProductUpdated::class]);
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create();

    $this->deleteJson($this->centralUrl("/admin/catalog/products/{$product->id}"))->assertOk();
    expect($product->refresh()->trashed())->toBeTrue();
    $this->getJson($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertNotFound();

    $this->patchJson($this->centralUrl("/admin/catalog/products/{$product->id}/restore"))->assertOk();
    expect($product->refresh()->trashed())->toBeFalse();
    $this->patchJson($this->centralUrl("/admin/catalog/products/{$product->id}/restore"))->assertUnprocessable();
    $this->patchJson($this->centralUrl('/admin/catalog/products/999999/restore'))->assertNotFound();

    Event::assertDispatched(ProductUpdated::class, 2);
});

it('exports the filtered list as CSV', function (): void {
    $this->actingAs(catalogAdmin(['catalog.products.view', 'catalog.products.export']), 'admin');
    Product::factory()->create(['sku' => 'NG-EXPORT', 'name' => ['en' => 'Linen Dress', 'ar' => 'فستان'], 'base_price' => Money::of(49)]);
    Product::factory()->draft()->create(['sku' => 'NG-DRAFT']);

    $csv = $this->get($this->centralUrl('/admin/catalog/products/export?status=published'))->assertOk()->streamedContent();

    expect($csv)->toContain('sku,name_en,name_ar')
        ->toContain('NG-EXPORT,"Linen Dress",فستان,published,49.00')
        ->not->toContain('NG-DRAFT');
});

it('shows per-country and per-variant prices computed by Commerce pricing', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    $product = Product::factory()->create([
        'base_price' => Money::of(10),
        'weight_grams' => 500,
        'fixed_shipping_costs' => [(string) $sa->id => 500],
    ]);
    $variant = ProductVariant::factory()->for($product)->create(['price' => Money::of(12)]);

    $expected = CountryPriceTable::forCatalog(Money::of(12), [(string) $sa->id => Money::of(5), (string) $eg->id => Money::zero()], null, 0.0, [$sa->id, $eg->id]);
    expect($expected['prices'][(string) $sa->id]->toDecimal())->toBe('17.00');

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/prices"))
        ->assertOk()
        ->assertSee('$17.00')
        ->assertSee('$12.00')
        ->assertSee('$5.00');

    $csv = $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/prices/export"))->assertOk()->streamedContent();
    expect($csv)->toContain('SA,')->toContain(',12.00,,5.00,17.00')->toContain(',12.00,,0.00,12.00');

    $variant->delete();
    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/prices"))->assertSee('$15.00')->assertSee('$10.00');
});

it('searches tenants for the assignment picker', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $tenant = productTenant();

    $this->getJson($this->centralUrl('/admin/catalog/products/tenants?q=megz'))
        ->assertOk()->assertJsonPath('results.0', ['id' => $tenant->id, 'text' => 'Megz Store']);
});

it('renders the edit form with its variant builder state', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $variant = ProductVariant::factory()->create(['sku' => 'NG-VAR-1']);
    $variant->product->update(['status' => ProductStatus::Published]);

    $this->get($this->centralUrl("/admin/catalog/products/{$variant->product_id}/edit"))
        ->assertOk()
        ->assertSee('NG-VAR-1')
        ->assertSee(__('catalog::products.sections.variants'))
        ->assertSee(__('catalog::products.variants.stock'));
});

it('assigns a product to badge lists from the form and removes it again', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create(['sku' => 'NG-10293']);

    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['badges' => [BadgeType::Featured->value]]))->assertOk();
    expect(ProductBadgeAssignment::query()->where('product_id', $product->id)->count())->toBe(1);

    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['badges' => []]))->assertOk();
    expect(ProductBadgeAssignment::query()->where('product_id', $product->id)->count())->toBe(0);
});

it('uploads gallery videos next to the images: never primary, kept on reorder, poster job, size limit from settings', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'images' => [UploadedFile::fake()->create('clip.mp4', 900, 'video/mp4'), UploadedFile::fake()->image('a.png', 400, 400)],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $product = Product::query()->sole();
    [$video, $image] = $product->gallery->all();
    expect($video->type)->toBe(MediaType::Video)
        ->and($video->is_primary)->toBeFalse()
        ->and($video->path)->toEndWith('.mp4')
        ->and($image->is_primary)->toBeTrue()
        ->and($product->media->pluck('id')->all())->toBe([$image->id])
        ->and($product->primaryImageUrl())->toBe($image->url());
    Storage::disk('public')->assertExists($video->path);
    Queue::assertPushed(GenerateVideoPoster::class, fn (GenerateVideoPoster $job): bool => $job->mediaId === $video->id);
    Queue::assertPushed(ConvertCatalogMedia::class, 1);

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertOk()
        ->assertSee('data-video-tile', false)
        ->assertSee($video->url(), false);

    // Reorder: the video first is still not primary; removing it deletes the file.
    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['_method' => 'PUT', 'images_keep' => [$video->id, $image->id]]), ['Accept' => 'application/json'])->assertOk();
    expect($video->refresh()->is_primary)->toBeFalse()->and($video->sort_order)->toBe(0)->and($image->refresh()->is_primary)->toBeTrue();

    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['_method' => 'PUT', 'images_keep' => [$image->id]]), ['Accept' => 'application/json'])->assertOk();
    expect($product->gallery()->count())->toBe(1);
    Storage::disk('public')->assertMissing($video->path);

    // Wrong type and the DB size limit (Admin › Settings › Platform › Commerce).
    config(['commerce.product_video_max_mb' => 1]);
    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'sku' => 'NG-V2',
        'images' => [UploadedFile::fake()->create('big.mp4', 2048, 'video/mp4')],
    ]), ['Accept' => 'application/json'])->assertJsonValidationErrors('images.0');
    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'sku' => 'NG-V3',
        'images' => [UploadedFile::fake()->create('clip.avi', 100, 'video/x-msvideo')],
    ]), ['Accept' => 'application/json'])->assertJsonValidationErrors('images.0');
});

it('writes a poster frame for a gallery video only when ffmpeg is available', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();
    $media = $product->gallery()->create(['type' => MediaType::Video, 'disk' => 'public', 'path' => 'catalog/products/v.mp4', 'mime_type' => 'video/mp4', 'size' => 10]);
    Storage::disk('public')->put('catalog/products/v.mp4', 'not-a-real-video');

    $generator = Mockery::mock(VideoPosterGenerator::class);
    $generator->shouldReceive('generate')->once()->with('public', 'catalog/products/v.mp4')->andReturn('catalog/products/v-poster.jpg');
    (new GenerateVideoPoster($media->id))->handle($generator);
    expect($media->refresh()->poster_path)->toBe('catalog/products/v-poster.jpg')->and($media->conversion_status)->toBe(MediaConversionStatus::Done);

    $missing = Mockery::mock(VideoPosterGenerator::class);
    $missing->shouldReceive('generate')->once()->andReturnNull();
    $other = $product->gallery()->create(['type' => MediaType::Video, 'disk' => 'public', 'path' => 'catalog/products/w.mp4', 'size' => 10]);
    (new GenerateVideoPoster($other->id))->handle($missing);
    expect($other->refresh()->poster_path)->toBeNull()->and($other->conversion_status)->toBe(MediaConversionStatus::Skipped);
});

it('shows the original upload while its conversion is pending and the webp once it is done', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $product = Product::query()->sole();
    $media = $product->media->sole();
    Storage::disk('public')->assertExists($media->path);
    expect($media->conversion_status)->toBe(MediaConversionStatus::Pending)
        ->and($media->bestUrl())->toBe($media->url())
        ->and($product->primaryImageUrl())->toBe($media->url());

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertOk()
        ->assertSee('src="'.$media->url().'"', false);

    (new ConvertCatalogMedia($media->id))->handle(app(ImageVariantConverter::class));
    $media->refresh();
    expect($media->bestUrl())->toBe(Storage::disk('public')->url($media->webp_path));

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertOk()
        ->assertSee('src="'.$media->bestUrl().'"', false);
});

it('saves a real image upload on edit and keeps the existing gallery', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $this->post($this->centralUrl('/admin/catalog/products'), productPayload(['images' => [UploadedFile::fake()->image('a.jpg', 400, 400)]]), ['Accept' => 'application/json'])->assertCreated();
    $product = Product::query()->sole();
    $first = $product->media->sole();

    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        '_method' => 'PUT',
        'images_keep' => [$first->id],
        'images' => [UploadedFile::fake()->image('b.png', 600, 600)],
    ]), ['Accept' => 'application/json'])->assertOk();

    $gallery = $product->gallery()->get();
    expect($gallery)->toHaveCount(2)
        ->and($gallery[0]->id)->toBe($first->id)
        ->and($gallery[0]->is_primary)->toBeTrue()
        ->and($gallery[1]->is_primary)->toBeFalse();
    Storage::disk('public')->assertExists($gallery[1]->path);
});

it('keeps a separate primary image before the gallery and replaces it on upload', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload([
        'primary_image' => [UploadedFile::fake()->image('main.png', 400, 400)],
        'images' => [UploadedFile::fake()->image('g1.png', 400, 400), UploadedFile::fake()->image('g2.png', 400, 400)],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $product = Product::query()->sole();
    [$main, $g1, $g2] = $product->gallery->all();
    expect($main->is_primary)->toBeTrue()->and($main->sort_order)->toBe(0)
        ->and($g1->is_primary)->toBeFalse()->and($g2->is_primary)->toBeFalse()
        ->and($product->primaryImageUrl())->toBe($main->url());

    // Edit shows it in the primary section; reorder the gallery and keep the primary.
    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertOk()
        ->assertSee('name="primary_image_keep[]" value="'.$main->id.'"', false)
        ->assertSee('name="images_keep[]" value="'.$g1->id.'"', false)
        ->assertDontSee('name="images_keep[]" value="'.$main->id.'"', false);
    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['_method' => 'PUT', 'primary_image_keep' => [$main->id], 'images_keep' => [$g2->id, $g1->id]]), ['Accept' => 'application/json'])->assertOk();
    expect($product->gallery()->pluck('id')->all())->toBe([$main->id, $g2->id, $g1->id])->and($main->refresh()->is_primary)->toBeTrue();

    // A new primary upload replaces the old one (file deleted); the gallery stays.
    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['_method' => 'PUT', 'primary_image' => [UploadedFile::fake()->image('new.png', 400, 400)], 'images_keep' => [$g2->id, $g1->id]]), ['Accept' => 'application/json'])->assertOk();
    $gallery = $product->gallery()->get();
    expect($gallery)->toHaveCount(3)->and($gallery[0]->id)->not->toBe($main->id)->and($gallery[0]->is_primary)->toBeTrue()->and($gallery->where('is_primary', true))->toHaveCount(1);
    Storage::disk('public')->assertMissing($main->path);

    // Removing the primary image makes the first gallery image the primary one.
    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['_method' => 'PUT', 'images_keep' => [$g2->id, $g1->id]]), ['Accept' => 'application/json'])->assertOk();
    expect($g2->refresh()->is_primary)->toBeTrue()->and($product->gallery()->count())->toBe(2);

    $this->post($this->centralUrl('/admin/catalog/products'), productPayload(['sku' => 'NG-P2', 'primary_image' => [UploadedFile::fake()->image('a.png', 400, 400), UploadedFile::fake()->image('b.png', 400, 400)]]), ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('primary_image');
});

it('accepts only the deepest category level, keeps existing parent categories and shows them as tags', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $parent = Category::factory()->create();
    $leaf = Category::factory()->create(['parent_id' => $parent->id]);

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['category_ids' => [$parent->id]]))
        ->assertJsonValidationErrors(['category_ids' => __('catalog::products.categories.leaf_only')]);
    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'category_ids', 'category_ids' => [$parent->id]])->assertJsonValidationErrors('category_ids');
    $this->postJson($this->centralUrl('/admin/catalog/products/validate'), ['field' => 'sku', 'sku' => 'FREE', 'category_ids' => [$parent->id]])->assertNoContent();

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['category_ids' => [$leaf->id]]))->assertCreated();
    $product = Product::query()->sole();

    // Older data with a parent category still saves.
    $product->categories()->sync([$parent->id, $leaf->id]);
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['category_ids' => [$parent->id, $leaf->id]]))->assertOk();

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/edit"))->assertOk()
        ->assertSee('data-category-tag data-id="'.$leaf->id.'"', false)
        ->assertSee('"leaf":true', false);
});

it('notifies only the stores that newly see a published product', function (): void {
    Event::fake([ProductOfferedToStores::class]);
    $this->actingAs(catalogAdmin(), 'admin');
    [$one, $two] = [productTenant(), productTenant()];

    $this->postJson($this->centralUrl('/admin/catalog/products'), productPayload(['status' => 'draft', 'assignment_scope' => 'selected', 'tenant_ids' => [$one->id]]))->assertCreated();
    Event::assertNotDispatched(ProductOfferedToStores::class);
    $product = Product::query()->sole();

    // Published: the assigned store is told; adding a second store tells only that one.
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['assignment_scope' => 'selected', 'tenant_ids' => [$one->id]]))->assertOk();
    Event::assertDispatched(ProductOfferedToStores::class, fn (ProductOfferedToStores $event): bool => $event->tenantIds === [$one->id] && ! $event->allStores);
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['assignment_scope' => 'selected', 'tenant_ids' => [$one->id, $two->id]]))->assertOk();
    Event::assertDispatched(ProductOfferedToStores::class, fn (ProductOfferedToStores $event): bool => $event->tenantIds === [$two->id]);

    // All stores: everyone except the ones that already had it; saving again is silent.
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['assignment_scope' => 'all']))->assertOk();
    Event::assertDispatched(ProductOfferedToStores::class, fn (ProductOfferedToStores $event): bool => $event->allStores && $event->exceptTenantIds === [$one->id, $two->id]);
    Event::assertDispatchedTimes(ProductOfferedToStores::class, 3);
    $this->putJson($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload(['assignment_scope' => 'all']))->assertOk();
    Event::assertDispatchedTimes(ProductOfferedToStores::class, 3);
});

it('sends the new-product notification to the owner and queues it for the store staff', function (): void {
    Notification::fake();
    Queue::fake();
    $product = Product::factory()->create(['sku' => 'NG-NEW', 'name' => ['en' => 'Linen Dress', 'ar' => 'فستان']]);
    $mailOnly = productTenant();
    $mailOnly->forceFill(['owner_email' => 'owner-'.Str::lower(Str::random(6)).'@example.test'])->save();
    $other = productTenant();
    $suspended = productTenant();
    $suspended->forceFill(['status' => TenantStatus::Suspended])->save();

    app(NotifyStoresOfOfferedProduct::class)->handle(new ProductOfferedToStores($product->id, allStores: true, exceptTenantIds: [$other->id]));

    Notification::assertSentOnDemand(RequestUpdateNotification::class, fn (RequestUpdateNotification $notification, array $channels, object $notifiable): bool => $notification->eventName === 'product_assigned'
        && $notification->parameters['sku'] === 'NG-NEW'
        && $notifiable->routes['mail'] === $mailOnly->owner_email);
    Queue::assertPushed(NotifyStoreStaff::class, fn (NotifyStoreStaff $job): bool => $job->tenantId === $mailOnly->id && $job->vendorRoute === 'catalog.products.new.show' && $job->routeParameters === ['central' => $product->id]);
    Queue::assertNotPushed(NotifyStoreStaff::class, fn (NotifyStoreStaff $job): bool => in_array($job->tenantId, [$other->id, $suspended->id], true));
    expect(__('catalog::requests.notifications.product_assigned.title'))->not->toBe('catalog::requests.notifications.product_assigned.title');
});

it('stores every uploaded primary image, gallery image and video as a real file', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create(['sku' => 'NG-10293']);

    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        '_method' => 'PUT',
        'primary_image' => [UploadedFile::fake()->image('main.jpg', 400, 400)],
        'images' => [UploadedFile::fake()->image('g.png', 400, 400), UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4')],
    ]), ['Accept' => 'application/json'])->assertOk();

    $media = $product->gallery()->get();
    expect($media)->toHaveCount(3)
        ->and($media->pluck('type')->map->value->all())->toBe(['image', 'image', 'video']);

    foreach ($media as $item) {
        expect($item->path)->toStartWith('catalog/products/')->not->toBe('0')
            ->and($item->conversion_status)->toBe(MediaConversionStatus::Pending);
        Storage::disk('public')->assertExists($item->path);
    }

    expect($media[0]->path)->toEndWith('.jpg')->and($media[2]->path)->toEndWith('.mp4');
});

it('refuses to save a media row when the disk cannot store the file', function (): void {
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create(['sku' => 'NG-10293']);
    $disk = Mockery::mock(Storage::fake('public'))->makePartial();
    $disk->shouldReceive('putFileAs')->andReturnFalse();
    Storage::set('public', $disk);

    $this->post($this->centralUrl("/admin/catalog/products/{$product->id}"), productPayload([
        '_method' => 'PUT',
        'images' => [UploadedFile::fake()->image('g.png', 400, 400)],
    ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJson(['message' => __('catalog::products.media_not_saved', ['name' => 'g.png'])]);

    expect($product->gallery()->count())->toBe(0);
});
