<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Localization\Models\Country;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/CommerceHelpers.php';

/*
| ADM-026 / 026a central flash sales: permissions, Live / Scheduled / Ended tabs, list,
| product picker, create / edit / view modal with banner, validation, switch, delete.
*/

function flashSalePayload(array $productIds, array $overrides = []): array
{
    return array_merge([
        'title' => ['en' => 'White Friday Electronics', 'ar' => 'الجمعة البيضاء'],
        'discount_percentage' => '25%',
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addDays(3)->toDateString(),
        'product_ids' => $productIds,
        'is_active' => '1',
    ], $overrides);
}

it('redirects guests and forbids admins without flash sale permissions', function (): void {
    $sale = FlashSale::factory()->create();

    $this->get($this->centralUrl('/admin/promotions/flash-sales'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(commerceAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/promotions/flash-sales'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/promotions/flash-sales/data'))->assertForbidden();

    $this->actingAs(commerceAdmin(['flash-sales.view']), 'admin');
    $this->get($this->centralUrl('/admin/promotions/flash-sales'))->assertOk()->assertSee(__('commerce::flash_sales.subtitle'))->assertDontSee(__('commerce::flash_sales.add'));
    $this->get($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('flash-sale-view', false);
    $this->getJson($this->centralUrl('/admin/promotions/flash-sales/create'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/promotions/flash-sales/products'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/promotions/flash-sales'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}"), [])->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}/active"), ['is_active' => false])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}"))->assertForbidden();
});

it('lists sales per phase with counts, products and countries', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $live = FlashSale::factory()->create(['title' => ['en' => 'Kitchen Week', 'ar' => 'أسبوع المطبخ'], 'discount_percentage' => 15, 'banner_path' => 'promotions/flash-sales/kitchen.jpg']);
    $live->products()->sync(Product::factory()->count(2)->create()->pluck('id')->all());
    $live->countries()->sync([$sa->id]);
    FlashSale::factory()->scheduled()->create(['title' => ['en' => 'Mega Sale']]);
    FlashSale::factory()->ended()->create(['title' => ['en' => 'Old Sale']]);

    $this->get($this->centralUrl('/admin/promotions/flash-sales'))->assertOk()->assertSee(__('commerce::flash_sales.tabs.scheduled'));

    $data = fn (string $query): array => $this->getJson($this->centralUrl('/admin/promotions/flash-sales/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
    $row = $data('&phase=live')['data'][0];

    expect($data('&phase=live')['recordsFiltered'])->toBe(1)
        ->and($row['title'])->toContain('Kitchen Week')->toContain('kitchen.jpg')
        ->and($row['discount_percentage'])->toBe('15%')
        ->and(strip_tags($row['products_count']))->toBe('2')
        ->and($row['countries'])->toBe('SA')
        ->and($row['ends_at'])->toStartWith('in ')
        ->and($data('&phase=scheduled')['data'][0]['title'])->toContain('Mega Sale')
        ->and($data('&phase=ended')['recordsFiltered'])->toBe(1)
        ->and($data('&phase=live&search=kitch')['recordsFiltered'])->toBe(1)
        ->and($data('&phase=live&status=inactive')['recordsFiltered'])->toBe(0);
});

it('creates and edits a sale with products, countries, stores and a banner', function (): void {
    Storage::fake('public');
    $this->actingAs(commerceAdmin(['flash-sales.manage']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('salestore');
    $watch = Product::factory()->create(['name' => ['en' => 'Smart Watch Series 4', 'ar' => 'ساعة']]);
    $buds = Product::factory()->create();

    $this->get($this->centralUrl('/admin/promotions/flash-sales/create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('flash-sale-form', false);
    $this->getJson($this->centralUrl('/admin/promotions/flash-sales/products?q=smart'))->assertOk()
        ->assertJsonPath('results.0.id', $watch->id)
        ->assertJsonPath('pagination.more', false);

    $this->post($this->centralUrl('/admin/promotions/flash-sales'), flashSalePayload([$watch->id, $buds->id], [
        'country_ids' => [$sa->id],
        'store_scope' => 'selected',
        'tenant_ids' => [$tenant->id],
        'banner' => [UploadedFile::fake()->image('wf.png', 1200, 400)],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $sale = FlashSale::query()->sole();
    expect($sale->title)->toBe('White Friday Electronics')
        ->and($sale->getTranslation('title', 'ar'))->toBe('الجمعة البيضاء')
        ->and($sale->discount_percentage)->toBe(25.0)
        ->and($sale->products()->pluck('products.id')->sort()->values()->all())->toBe([$watch->id, $buds->id])
        ->and($sale->store_scope)->toBe(PromotionScope::SelectedStores)
        ->and($sale->tenants()->pluck('tenants.id')->all())->toBe([$tenant->id])
        ->and($sale->banner_path)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $sale->banner_path);

    $this->get($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}/edit"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Smart Watch Series 4');

    $banner = (string) $sale->banner_path;
    $this->putJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}"), flashSalePayload([$buds->id], ['discount_percentage' => '30']))->assertOk();
    $sale->refresh();
    expect($sale->discount_percentage)->toBe(30.0)
        ->and($sale->products()->count())->toBe(1)
        ->and($sale->tenants()->count())->toBe(0)
        ->and($sale->banner_path)->toBeNull(); // banner not kept → removed
    Storage::disk('public')->assertMissing($banner);

    $this->patchJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}/active"), ['is_active' => false])->assertOk();
    expect($sale->refresh()->is_active)->toBeFalse();
    $this->deleteJson($this->centralUrl("/admin/promotions/flash-sales/{$sale->id}"))->assertOk();
    expect(FlashSale::query()->count())->toBe(0);
});

it('validates the flash sale form', function (): void {
    $this->actingAs(commerceAdmin(['flash-sales.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/promotions/flash-sales'), [])
        ->assertJsonValidationErrors(['title.en', 'title.ar', 'discount_percentage', 'dates_from', 'dates_to', 'product_ids']);

    $this->postJson($this->centralUrl('/admin/promotions/flash-sales'), flashSalePayload([999999], [
        'discount_percentage' => '100',
        'dates_to' => now()->subDays(2)->toDateString(),
        'store_scope' => 'selected',
    ]))->assertJsonValidationErrors(['discount_percentage', 'dates_to', 'product_ids.0', 'tenant_ids']);
});
