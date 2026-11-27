<?php

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Exceptions\PromotionException;
use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\BannerInputData;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Events\BannersChanged;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\HiddenCentralBanner;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Modules\Storefront\Services\Banners\StoreBannerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__).'/Commerce/CommerceHelpers.php';

/*
| Banners (decision #65 b): admin default banners ADM-085 / 085a (permissions, country
| tabs, placement, upload, edit, switch, drag sort, delete), the store banner service API
| (VEN-035) and the ONE merged reader BannerCatalog (central ⊕ store, `source`, store
| scope, hidden central banners, countries trimmed to the store's selling countries,
| schedule, sort order).
*/

beforeEach(function (): void {
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
});

function bannerPayload(array $overrides = []): array
{
    return array_merge([
        'title' => ['en' => 'White Friday — up to 50% off', 'ar' => 'الجمعة البيضاء'],
        'subtitle' => ['en' => 'Electronics and home', 'ar' => ''],
        'cta_label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
        'placement' => 'home.hero',
        'link_url' => '/flash-sales/white-friday',
        'store_scope' => 'all',
        'is_active' => '1',
    ], $overrides);
}

function bannerTitles(array $banners): array
{
    return array_map(static fn (BannerData $banner): string => $banner->source.':'.$banner->title, $banners);
}

it('guards the admin banner pages with storefront.banners permissions', function (): void {
    $banner = Banner::factory()->create();

    $this->get($this->centralUrl('/admin/storefront/banners'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(commerceAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/storefront/banners'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/storefront/banners/data'))->assertForbidden();

    $this->actingAs(commerceAdmin(['storefront.banners.view']), 'admin');
    $this->get($this->centralUrl('/admin/storefront/banners'))->assertOk()->assertSee(__('engagement::banners.subtitle'))->assertDontSee(__('engagement::banners.add'));
    $this->getJson($this->centralUrl('/admin/storefront/banners/data?draw=1&start=0&length=100'))->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->getJson($this->centralUrl('/admin/storefront/banners/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/storefront/banners'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/storefront/banners/{$banner->id}"), [])->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/storefront/banners/{$banner->id}/active"), ['is_active' => false])->assertForbidden();
    $this->postJson($this->centralUrl('/admin/storefront/banners/sort'), ['ids' => [$banner->id]])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/storefront/banners/{$banner->id}"))->assertForbidden();
});

it('creates, lists per country tab, edits, switches, sorts and deletes central banners', function (): void {
    Storage::fake('public');
    Event::fake([BannersChanged::class]);
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/storefront/banners/create?country='.$this->sa->id), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('banner-form', false)->assertSee(__('engagement::banners.fields.mobile_image'));

    $this->postJson($this->centralUrl('/admin/storefront/banners'), bannerPayload(['image' => []]))->assertUnprocessable()->assertJsonValidationErrors('image');
    $this->postJson($this->centralUrl('/admin/storefront/banners'), bannerPayload(['link_url' => 'javascript:alert(1)', 'image' => [UploadedFile::fake()->image('a.jpg')]]))
        ->assertUnprocessable()->assertJsonValidationErrors('link_url');

    $this->postJson($this->centralUrl('/admin/storefront/banners'), bannerPayload([
        'country_ids' => [$this->sa->id],
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addDays(2)->toDateString(),
        'image' => [UploadedFile::fake()->image('white-friday.jpg', 1440, 280)],
        'mobile_image' => [UploadedFile::fake()->image('white-friday-m.jpg', 720, 360)],
    ]))->assertCreated();
    $this->postJson($this->centralUrl('/admin/storefront/banners'), bannerPayload([
        'title' => ['en' => 'New in: winter collection', 'ar' => 'وصل حديثًا'],
        'image' => [UploadedFile::fake()->image('winter.jpg', 1440, 280)],
    ]))->assertCreated();
    $this->postJson($this->centralUrl('/admin/storefront/banners'), bannerPayload([
        'title' => ['en' => 'Egypt promo strip', 'ar' => 'شريط مصر'],
        'placement' => 'home.promo',
        'country_ids' => [$this->eg->id],
        'image' => [UploadedFile::fake()->image('eg.jpg', 600, 300)],
    ]))->assertCreated();

    $first = Banner::query()->where('title->en', 'White Friday — up to 50% off')->firstOrFail();
    $second = Banner::query()->where('title->en', 'New in: winter collection')->firstOrFail();
    expect($first->image_width)->toBe(1440)->and($first->image_height)->toBe(280)
        ->and($first->countries->pluck('id')->all())->toBe([$this->sa->id])
        ->and($first->getTranslation('cta_label', 'ar'))->toBe('تسوق الآن')
        ->and($first->ends_at?->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($second->sort_order)->toBe($first->sort_order + 1);
    Storage::disk('public')->assertExists([$first->image_path, (string) $first->mobile_image_path]);

    $page = $this->get($this->centralUrl('/admin/storefront/banners?country='.$this->sa->id))->assertOk();
    $page->assertSee(__('engagement::banners.add'))->assertSee('data-banner-sort', false);

    $rows = fn (string $query): array => $this->getJson($this->centralUrl('/admin/storefront/banners/data?draw=1&start=0&length=100'.$query))->assertOk()->json('data');
    expect(array_map(static fn (array $row): string => strip_tags($row['banner']), $rows('&country_id='.$this->sa->id)))
        ->sequence(fn ($text) => $text->toContain('White Friday'), fn ($text) => $text->toContain('New in: winter collection'))
        ->and($rows('&country_id='.$this->ae->id))->toHaveCount(1)
        ->and($rows('&placement=home.promo'))->toHaveCount(1)
        ->and($rows('&placement=home.promo&country_id='.$this->sa->id))->toHaveCount(0)
        ->and($rows('&search=winter'))->toHaveCount(1)
        ->and(strip_tags($rows('')[0]['status']))->toContain(__('engagement::enums.banner_status.active'));

    $this->get($this->centralUrl("/admin/storefront/banners/{$first->id}/edit"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('White Friday');

    $oldImage = $first->image_path;
    $oldMobile = (string) $first->mobile_image_path;
    $this->putJson($this->centralUrl("/admin/storefront/banners/{$first->id}"), bannerPayload([
        'title' => ['en' => 'White Friday extended', 'ar' => 'الجمعة البيضاء ممتدة'],
        'store_scope' => 'selected',
        'tenant_ids' => [],
    ]))->assertUnprocessable()->assertJsonValidationErrors('tenant_ids');
    $this->putJson($this->centralUrl("/admin/storefront/banners/{$first->id}"), bannerPayload([
        'title' => ['en' => 'White Friday extended', 'ar' => 'الجمعة البيضاء ممتدة'],
        'image' => [UploadedFile::fake()->image('new.jpg', 1440, 280)],
        'image_keep' => [],
    ]))->assertOk();
    $first->refresh();
    expect($first->title)->toBe('White Friday extended')->and($first->countries)->toHaveCount(0)->and($first->mobile_image_path)->toBeNull();
    Storage::disk('public')->assertMissing([$oldImage, $oldMobile]);
    Storage::disk('public')->assertExists($first->image_path);

    $this->patchJson($this->centralUrl("/admin/storefront/banners/{$first->id}/active"), ['is_active' => false])->assertOk();
    expect($first->fresh()?->is_active)->toBeFalse();

    $this->postJson($this->centralUrl('/admin/storefront/banners/sort'), ['ids' => [$second->id, $first->id]])->assertOk();
    expect($second->fresh()?->sort_order)->toBeLessThan((int) $first->fresh()?->sort_order);

    $this->deleteJson($this->centralUrl("/admin/storefront/banners/{$first->id}"))->assertOk();
    expect(Banner::query()->find($first->id))->toBeNull();
    Storage::disk('public')->assertMissing($first->image_path);
    Event::assertDispatched(BannersChanged::class);
});

it('merges central and store banners for the storefront with scope, hiding, countries and schedule', function (): void {
    $tenant = $this->createTenant('banners', ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $other = $this->createTenant('elsewhere');

    $everywhere = Banner::factory()->create(['title' => ['en' => 'Central all'], 'sort_order' => 2]);
    Banner::factory()->create(['title' => ['en' => 'Central SA'], 'sort_order' => 4])->countries()->sync([$this->sa->id]);
    Banner::factory()->create(['title' => ['en' => 'Central Egypt only'], 'sort_order' => 1])->countries()->sync([$this->eg->id]);
    Banner::factory()->create(['title' => ['en' => 'Central other store'], 'store_scope' => PromotionScope::SelectedStores])->tenants()->sync([$other->id]);
    Banner::factory()->create(['title' => ['en' => 'Central selected'], 'store_scope' => PromotionScope::SelectedStores, 'sort_order' => 5])->tenants()->sync([$tenant->id]);
    $hidden = Banner::factory()->create(['title' => ['en' => 'Central hidden'], 'sort_order' => 3]);
    Banner::factory()->scheduled()->create(['title' => ['en' => 'Central later']]);
    Banner::factory()->inactive()->create(['title' => ['en' => 'Central off']]);
    Banner::factory()->create(['title' => ['en' => 'Central promo'], 'placement' => BannerPlacement::HomePromo]);

    $tenant->run(function () use ($hidden): void {
        HiddenCentralBanner::query()->create(['banner_id' => $hidden->id]);
        StoreBanner::query()->create(['placement' => 'home.hero', 'title' => ['en' => 'Store all'], 'image_path' => 'storefront/banners/s1.webp', 'sort_order' => 2, 'is_active' => true]);
        $ae = StoreBanner::query()->create(['placement' => 'home.hero', 'title' => ['en' => 'Store AE'], 'image_path' => 'storefront/banners/s2.webp', 'sort_order' => 1, 'is_active' => true]);
        $ae->countryRows()->create(['country_id' => $this->ae->id]);
        StoreBanner::query()->create(['placement' => 'home.hero', 'title' => ['en' => 'Store ended'], 'image_path' => 'storefront/banners/s3.webp', 'sort_order' => 1, 'is_active' => true, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
    });

    $tenant->run(function () use ($everywhere): void {
        $catalog = app(BannerCatalog::class);

        expect(bannerTitles($catalog->forPlacement('home.hero', $this->sa->id, 'en')))->toBe([
            'central:Central all', 'vendor:Store all', 'central:Central SA', 'central:Central selected',
        ])->and(bannerTitles($catalog->forPlacement('home.hero', $this->ae->id, 'en')))->toBe([
            'vendor:Store AE', 'central:Central all', 'vendor:Store all', 'central:Central selected',
        ])->and(bannerTitles($catalog->forPlacement('home.promo', $this->sa->id, 'en')))->toBe(['central:Central promo'])
            ->and($catalog->forPlacement('nowhere', $this->sa->id))->toBe([]);

        $first = $catalog->forPlacement('home.hero', $this->sa->id, 'en')[0];
        expect($first->id)->toBe($everywhere->id)->and($first->imageUrl)->toContain($everywhere->image_path)
            ->and($first->width)->toBe(1440)->and($first->toArray())->toHaveKeys(['source', 'image_url', 'mobile_image_url', 'cta_label']);

        $all = $catalog->all(BannerPlacement::HomeHero);
        expect(collect($all)->firstWhere('title', 'Central hidden')?->hidden)->toBeTrue()
            ->and(bannerTitles($all))->toContain('central:Central later', 'vendor:Store ended')->not->toContain('central:Central Egypt only', 'central:Central other store');
    });

    $other->run(fn () => expect(bannerTitles(app(BannerCatalog::class)->forPlacement('home.hero', $this->eg->id, 'en')))->toContain('central:Central other store', 'central:Central Egypt only', 'central:Central hidden'));

    expect(fn () => app(BannerCatalog::class)->forPlacement('home.hero', $this->sa->id))->toThrow(PromotionException::class);
});

it('manages store banners through the vendor service API', function (): void {
    Storage::fake('public');
    Event::fake([BannersChanged::class]);
    $tenant = $this->createTenant('vendorbanners', ['selling_country_ids' => [$this->sa->id]]);
    $central = Banner::factory()->create(['title' => ['en' => 'Platform banner'], 'sort_order' => 1]);

    $tenant->run(function () use ($central, $tenant): void {
        $service = app(StoreBannerService::class);
        $input = fn (string $title, array $extra = []): BannerInputData => new BannerInputData(...array_merge([
            'placement' => BannerPlacement::HomeHero,
            'title' => ['en' => $title, 'ar' => $title],
            'linkUrl' => '/offers',
            'countryIds' => [$this->sa->id],
            'image' => UploadedFile::fake()->image($title.'.jpg', 1440, 280),
        ], $extra));

        $one = $service->create($input('Store one'));
        $two = $service->create($input('Store two', ['mobileImage' => UploadedFile::fake()->image('m.jpg', 720, 360)]));
        expect($two->sort_order)->toBe($one->sort_order + 1)->and($two->countryIds())->toBe([$this->sa->id])->and($service->count())->toBe(2);
        Storage::disk('public')->assertExists([$one->image_path, (string) $two->mobile_image_path]);

        $service->update($one, $input('Store one renamed', ['image' => null, 'countryIds' => []]));
        expect($one->fresh()?->title)->toBe('Store one renamed')->and($one->fresh()?->countryIds())->toBe([]);

        $service->reorder([$two->id, $one->id]);
        $service->setCentralHidden($central->id, true);
        expect(bannerTitles(app(BannerCatalog::class)->forPlacement('home.hero', $this->sa->id, 'en')))->toBe(['vendor:Store two', 'vendor:Store one renamed']);

        $service->setCentralHidden($central->id, false);
        $service->setActive($two, false);
        expect(bannerTitles(app(BannerCatalog::class)->forPlacement('home.hero', $this->sa->id, 'en')))->toBe(['central:Platform banner', 'vendor:Store one renamed']);

        $path = (string) $two->mobile_image_path;
        $service->delete($two);
        expect(StoreBanner::query()->count())->toBe(1);
        Storage::disk('public')->assertMissing($path);

        Event::assertDispatched(BannersChanged::class, fn (BannersChanged $event): bool => $event->tenantId === $tenant->id);
    });
});

it('names the banner in its drag handle label (RQA-ADM-009)', function (): void {
    $this->actingAs(commerceAdmin(['storefront.banners.view', 'storefront.banners.manage']), 'admin');
    $banner = Banner::factory()->create(['title' => ['en' => 'Winter drop', 'ar' => 'تشكيلة الشتاء']]);

    $cell = $this->getJson($this->centralUrl('/admin/storefront/banners/data?draw=1&start=0&length=100'))->assertOk()->json('data.0.banner');

    expect($cell)->toContain('aria-label="'.e(__('engagement::banners.drag_named', ['name' => $banner->title])).'"')
        ->and($cell)->toContain('Winter drop');
});
