<?php

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Storefront\DTOs\StorePageData;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Enums\SystemStorePage;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Illuminate\Validation\ValidationException;

/*
| Store CMS pages (SF-ECO-034, VEN-033 service API): system pages seeded per store
| (provisioning listener, sync command), public page + contact page with the support
| form (SF-ECO-035), footer links, sitemap entries, custom pages.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('pages', ['name' => 'Manti']);
});

it('seeds the system pages in arabic and english once', function (): void {
    $this->tenant->run(function (): void {
        $service = app(StorePageService::class);

        expect($service->ensureSystemPages('Manti'))->toBe(count(SystemStorePage::cases()))
            ->and($service->ensureSystemPages('Manti'))->toBe(0);

        $about = StorePage::query()->where('slug', 'about')->sole();
        expect($about->type)->toBe(StorePageType::System)
            ->and($about->getTranslation('title', 'ar'))->toBe(__st('pages.system.about.title', [], 'ar'))
            ->and($about->getTranslation('body', 'en'))->toContain('Welcome to Manti')
            ->and(StorePage::query()->pluck('slug')->sort()->values()->all())->toBe(collect(SystemStorePage::cases())->map->value->sort()->values()->all());
    });
});

it('creates the pages on provisioning and for existing stores by command', function (): void {
    event(new TenantProvisioned($this->tenant));
    $this->tenant->run(fn () => expect(StorePage::query()->count())->toBe(count(SystemStorePage::cases())));

    $this->tenant->run(fn () => StorePage::query()->where('slug', 'faq')->delete());
    $this->artisan('storefront:sync-store-pages', ['--tenant' => [$this->tenant->id]])->assertSuccessful();
    $this->tenant->run(fn () => expect(StorePage::query()->where('slug', 'faq')->exists())->toBeTrue());
});

it('renders a published page with the pages menu, and the contact page with the support form', function (): void {
    $this->tenant->run(function (): void {
        app(StorePageService::class)->ensureSystemPages('Manti');
        StorePage::query()->where('slug', 'privacy')->update(['is_active' => false]);
    });

    $this->get($this->tenantUrl($this->tenant, '/pages/shipping-info'))
        ->assertOk()
        ->assertSee(__st('pages.system.shipping_info.title'))
        ->assertSee('Processing time')
        ->assertSee(__st('pages.help_box'))
        ->assertSee(__st('pages.system.about.title'))
        ->assertDontSee(__st('pages.system.privacy.title'));

    $this->get($this->tenantUrl($this->tenant, '/pages/contact'))
        ->assertOk()
        ->assertSee(__st('support.title'))
        ->assertSee('data-phone-input', false)
        ->assertSee(route('storefront.support.store', [], false), false)
        ->assertSee(route('storefront.newsletter.store', [], false), false)
        ->assertSee(__st('support.subjects.order_issue'));

    $this->get($this->tenantUrl($this->tenant, '/pages/privacy'))->assertNotFound();
    $this->get($this->tenantUrl($this->tenant, '/pages/nothing-here'))->assertNotFound();
});

it('exposes footer links and sitemap entries and manages custom pages', function (): void {
    $this->tenant->run(function (): void {
        $service = app(StorePageService::class);
        $reader = app(StorePageReader::class);
        $service->ensureSystemPages('Manti');

        $custom = $service->create(new StorePageData(title: ['en' => 'Our story', 'ar' => 'قصتنا'], body: ['en' => '<p>Since 2020 <script>alert(1)</script></p>'], showInFooter: false));
        expect($custom->slug)->toBe('our-story')->and($custom->type)->toBe(StorePageType::Custom)
            ->and($custom->getTranslation('body', 'en'))->not->toContain('<script')
            ->and($service->create(new StorePageData(title: ['en' => 'Our story']))->slug)->toBe('our-story-2');

        $links = $reader->footerLinks();
        expect(array_column($links, 'url'))->not->toContain(route('storefront.pages.show', 'our-story'))->toContain(route('storefront.pages.show', 'our-story-2'))
            ->and(collect($links)->firstWhere('title', __st('pages.system.terms.title'))['group'])->toBe('legal')
            ->and(count($reader->sitemapEntries()))->toBe(count(SystemStorePage::cases()) + 2);

        $service->update($custom, new StorePageData(title: ['en' => 'Our journey'], slug: 'journey', isActive: false));
        expect($custom->fresh()?->slug)->toBe('journey')->and($reader->find('journey'))->toBeNull();

        $service->delete($custom);
        expect(fn () => $service->delete(StorePage::query()->where('slug', 'about')->sole()))->toThrow(ValidationException::class);
    });
});
