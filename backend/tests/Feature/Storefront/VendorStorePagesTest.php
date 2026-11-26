<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Illuminate\Support\Str;

/*
| VEN-033 / 033a Vendor › Your store › Pages: list (search, status / type filters), publish
| and footer switches, create / edit per language (slug, rich body sanitised, meta), delete
| custom pages only, permissions, tenant isolation, and the effect on the storefront.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('vpg-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->tenant->run(fn () => app(StorePageService::class)->ensureSystemPages('Megz'));
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/pages'.$path);
    $this->data = fn (string $query = ''): array => $this->getJson(($this->url)('/data?draw=1&start=0&length=50'.$query))->assertOk()->json();
    $this->page = fn (string $slug): StorePage => $this->tenant->run(fn (): StorePage => StorePage::query()->where('slug', $slug)->sole());
});

function vendorStorePagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => ['en' => 'Size guide', 'ar' => 'دليل المقاسات'],
        'body' => ['en' => '<h2>Find your size</h2><p>Measure twice.</p><script>alert(1)</script>', 'ar' => '<p>قِس مرتين.</p>'],
        'meta_description' => ['en' => 'How our sizes fit', 'ar' => ''],
        'slug' => '',
        'is_active' => '1',
        'show_in_footer' => '1',
    ], $overrides);
}

it('sends guests to the login and guards every endpoint with store.pages permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.pages.view']);

        return [$none, $viewer];
    });
    $about = ($this->page)('about');

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('content::store_pages.subtitle'))->assertDontSee(__('content::store_pages.add'));
    expect(($this->data)()['data'][0]['actions'])->not->toContain('data-confirm-url');
    $this->getJson(($this->url)('/create'))->assertForbidden();
    $this->getJson(($this->url)("/{$about->id}/edit"))->assertForbidden();
    $this->postJson(($this->url)(), vendorStorePagePayload())->assertForbidden();
    $this->putJson(($this->url)("/{$about->id}"), vendorStorePagePayload())->assertForbidden();
    $this->patchJson(($this->url)("/{$about->id}/flag"), ['is_active' => 0])->assertForbidden();
    $this->deleteJson(($this->url)("/{$about->id}"))->assertForbidden();
});

it('lists the store pages with search, status and type filters', function (): void {
    $this->tenant->run(fn () => StorePage::query()->where('slug', 'faq')->update(['is_active' => false]));
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertSee(__('content::store_pages.add'))->assertSee('vendor-store-pages', false);

    $all = ($this->data)();
    expect($all['recordsTotal'])->toBe(7)
        ->and($all['data'][0]['page'])->toContain('/pages/about')->toContain(__('content::store_pages.system_badge'))
        ->and($all['data'][0]['status'])->toContain(__('content::store_pages.status.published'))->toContain('data-switch-url')
        // System pages cannot be deleted: no delete action, a locked hint instead.
        ->and($all['data'][0]['actions'])->toContain(__('content::store_pages.system_locked'))->not->toContain('data-confirm-method="DELETE"');

    expect(($this->data)('&search=shipping')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('&status=draft')['data'][0]['page'])->toContain('/pages/faq')
        ->and(($this->data)('&status=draft')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('&type=custom')['recordsFiltered'])->toBe(0);
});

it('creates, edits, switches and deletes a custom page; system pages keep their slug and stay', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/create'))->assertOk()->assertSee('data-content-form', false)->assertSee(__('content::store_pages.fields.slug'));
    $this->postJson(($this->url)(), vendorStorePagePayload(['title' => ['en' => '', 'ar' => ''], 'slug' => 'Bad Slug!']))
        ->assertUnprocessable()->assertJsonValidationErrors(['title.en', 'title.ar', 'slug']);
    $this->postJson(($this->url)(), vendorStorePagePayload(['meta_description' => ['en' => str_repeat('a', 161)]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['meta_description.en']);

    $created = $this->postJson(($this->url)(), vendorStorePagePayload())->assertCreated()->json();
    $page = ($this->page)('size-guide');
    expect($created['redirect'])->toContain("/admin/store/pages/{$page->id}/edit")
        ->and($page->type)->toBe(StorePageType::Custom)
        ->and($page->getTranslation('title', 'ar'))->toBe('دليل المقاسات')
        ->and($page->getTranslation('body', 'en'))->toContain('<h2>Find your size</h2>')->not->toContain('<script')
        ->and($page->getTranslation('meta_description', 'en'))->toBe('How our sizes fit');

    // A second page with the same title gets a unique slug.
    $this->postJson(($this->url)(), vendorStorePagePayload())->assertCreated();
    expect(($this->page)('size-guide-2')->id)->not->toBe($page->id);

    $this->get(($this->url)("/{$page->id}/edit"))->assertOk()->assertSee('Find your size', false)->assertSee('size-guide');
    $this->putJson(($this->url)("/{$page->id}"), vendorStorePagePayload([
        'title' => ['en' => 'Sizes', 'ar' => 'المقاسات'],
        'body' => ['ar' => ''],
        'slug' => 'sizes',
        'is_active' => '0',
    ]))->assertOk()->assertJsonPath('data.slug', 'sizes');

    $this->tenant->run(function () use ($page): void {
        $fresh = $page->fresh();
        expect($fresh->slug)->toBe('sizes')
            ->and($fresh->title)->toBe('Sizes')
            ->and($fresh->is_active)->toBeFalse()
            // A cleared language is removed, not kept.
            ->and($fresh->getTranslation('body', 'ar', false))->toBe('');
    });

    $about = ($this->page)('about');
    $this->putJson(($this->url)("/{$about->id}"), vendorStorePagePayload(['title' => ['en' => 'Our story', 'ar' => 'قصتنا'], 'slug' => 'our-story']))->assertOk();
    expect(($this->page)('about')->title)->toBe('Our story');

    $this->patchJson(($this->url)("/{$about->id}/flag"), ['show_in_footer' => 0])->assertOk()->assertJsonPath('message', __('content::store_pages.footer_off', ['title' => 'Our story']));
    $this->patchJson(($this->url)("/{$page->id}/flag"), ['is_active' => 1])->assertOk();
    $this->patchJson(($this->url)("/{$page->id}/flag"), [])->assertUnprocessable();
    $this->tenant->run(fn () => expect($about->fresh()->show_in_footer)->toBeFalse()->and($page->fresh()->is_active)->toBeTrue());

    $this->deleteJson(($this->url)("/{$about->id}"))->assertUnprocessable()->assertJsonValidationErrors(['page']);
    $this->deleteJson(($this->url)("/{$page->id}"))->assertOk();
    $this->tenant->run(fn () => expect(StorePage::query()->whereKey($page->id)->exists())->toBeFalse()->and(StorePage::query()->where('slug', 'about')->exists())->toBeTrue());
});

it('keeps each store to its own pages', function (): void {
    $other = $this->createTenant('vpg-o-'.Str::lower(Str::random(6)));
    $foreign = $other->run(fn (): StorePage => StorePage::query()->forceCreate(['id' => 900, 'slug' => 'secret', 'type' => StorePageType::Custom, 'title' => json_encode(['en' => 'Secret page'])]));
    $this->actingAsVendor($this->owner, $this->tenant);

    expect(collect(($this->data)()['data'])->pluck('page')->implode(' '))->not->toContain('Secret page');
    $this->getJson(($this->url)("/{$foreign->id}/edit"))->assertNotFound();
    $this->putJson(($this->url)("/{$foreign->id}"), vendorStorePagePayload())->assertNotFound();
    $this->deleteJson(($this->url)("/{$foreign->id}"))->assertNotFound();
    $other->run(fn () => expect(StorePage::query()->whereKey(900)->exists())->toBeTrue());
});

it('shows the changes on the storefront: published pages, footer links and drafts', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->url)(), vendorStorePagePayload())->assertCreated();

    $this->get($this->tenantUrl($this->tenant, '/pages/size-guide'))->assertOk()->assertSee('Find your size')->assertSee('Size guide');

    $page = ($this->page)('size-guide');
    $this->patchJson(($this->url)("/{$page->id}/flag"), ['is_active' => 0])->assertOk();
    $this->get($this->tenantUrl($this->tenant, '/pages/size-guide'))->assertNotFound();

    $about = ($this->page)('about');
    $this->patchJson(($this->url)("/{$about->id}/flag"), ['show_in_footer' => 0])->assertOk();
    $this->get($this->tenantUrl($this->tenant, '/pages/contact'))->assertOk()
        ->assertDontSee('href="'.$this->tenantUrl($this->tenant, 'pages/about').'"', false);
});
