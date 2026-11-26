<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| VEN-034 Vendor › Your store › Appearance: logo, colours & fonts (+ reset), promo bar,
| header links, footer (text + custom links), social links — permissions, validation,
| tenant isolation and the effect on the storefront (appearance stylesheet, store bar,
| footer, social icons).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('app-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->tenant->run(fn () => app(StorePageService::class)->ensureSystemPages('Megz'));
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/appearance'.$path);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->storefront = fn (): string => $this->get($this->tenantUrl($this->tenant, '/pages/about'))->assertOk()->getContent();
    $this->setting = fn (StoreSettingKey $key): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', $key->value)->first()?->value);
});

function appearanceLinkPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'placement' => 'header',
        'label' => ['en' => 'Track order', 'ar' => 'تتبع الطلب'],
        'url' => '/track',
        'is_active' => '1',
    ], $overrides);
}

it('sends guests to the login and guards every endpoint with store.appearance permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.appearance.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('content::store_appearance.read_only'))->assertDontSee('data-remote-modal="appearance-modal"', false);
    $this->postJson(($this->url)('/logo'), [])->assertForbidden();
    $this->putJson(($this->url)('/colors'), ['primary' => '#ff0000'])->assertForbidden();
    $this->deleteJson(($this->url)('/colors'))->assertForbidden();
    $this->putJson(($this->url)('/promo'), ['enabled' => 0])->assertForbidden();
    $this->putJson(($this->url)('/footer'), [])->assertForbidden();
    $this->getJson(($this->url)('/links/create'))->assertForbidden();
    $this->postJson(($this->url)('/links'), appearanceLinkPayload())->assertForbidden();
    $this->postJson(($this->url)('/links/sort'), ['placement' => 'header', 'ids' => [1]])->assertForbidden();
    $this->patchJson(($this->url)('/links/1/active'), ['is_active' => 0])->assertForbidden();
    $this->deleteJson(($this->url)('/links/1'))->assertForbidden();
    $this->getJson(($this->url)('/social/create'))->assertForbidden();
    $this->postJson(($this->url)('/social'), ['network' => 'instagram', 'url' => 'https://instagram.com/megz'])->assertForbidden();
    $this->deleteJson(($this->url)('/social/1'))->assertForbidden();
});

it('renders every tab with the theme defaults and empty states', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $html = $this->get(($this->url)())->assertOk()->getContent();
    foreach (['logo', 'colors', 'promo', 'header', 'footer', 'social'] as $tab) {
        expect($html)->toContain(e(__('content::store_appearance.tabs.'.$tab)));
    }
    expect($html)->toContain(__('content::store_appearance.logo.empty'))
        ->toContain(__('content::store_appearance.links.empty_header'))
        ->toContain(__('content::store_appearance.social.empty'))
        ->toContain('data-swatch="#ff4d00"')
        ->toContain(__('content::store_appearance.colors.palette'));

    // Theme defaults: no appearance stylesheet, no store bar on the storefront.
    expect(($this->storefront)())->not->toContain('/appearance/')->not->toContain('data-store-bar');
});

it('saves colours and fonts, applies them on the storefront and resets them', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)('/colors'), ['primary' => 'red', 'font_ltr' => 'tajawal-x', 'font_rtl' => 'inter'])
        ->assertUnprocessable()->assertJsonValidationErrors(['primary', 'font_ltr', 'font_rtl']);
    $this->putJson(($this->url)('/colors'), ['primary' => '#8A38F5', 'font_ltr' => 'manrope', 'font_rtl' => 'tajawal'])->assertOk();

    expect(($this->setting)(StoreSettingKey::AppearanceColors))->toBe(['primary' => '#8a38f5', 'font_ltr' => 'manrope', 'font_rtl' => 'tajawal']);

    $html = ($this->storefront)();
    expect($html)->toMatch('#<link rel="stylesheet" href="[^"]+/appearance/ecommet\.css\?v=[0-9a-f]{12}">#');
    preg_match('#href="([^"]+/appearance/ecommet\.css\?v=[0-9a-f]{12})"#', $html, $match);

    $css = $this->get(html_entity_decode($match[1]))->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    expect($css->getContent())->toContain('html:root {')
        ->toContain('--nog-primary: #8a38f5;')
        ->toContain('--sf-header: #8a38f5;')
        ->toContain('--eco-red: #8a38f5;')
        ->toContain("--nog-font-latin: 'Manrope'")
        ->toContain("--nog-font-arabic: 'Tajawal'")
        ->toContain('html .sf-page[data-theme-variant] {');
    $this->get($this->tenantUrl($this->tenant, '/appearance/unknown.css'))->assertNotFound();

    $this->get(($this->url)('?tab=colors'))->assertOk()->assertSee('value="#8a38f5"', false);

    $this->deleteJson(($this->url)('/colors'))->assertOk()->assertJsonPath('redirect', fn (string $url) => str_contains($url, 'tab=colors'));
    expect(($this->setting)(StoreSettingKey::AppearanceColors))->toBeNull()
        ->and(($this->storefront)())->not->toContain('/appearance/ecommet.css');

    // "Theme default" (empty colour) with no font is the same as a reset.
    $this->putJson(($this->url)('/colors'), ['primary' => '', 'font_ltr' => '', 'font_rtl' => ''])->assertOk();
    expect(($this->setting)(StoreSettingKey::AppearanceColors))->toBeNull();
});

it('saves the promo bar and shows it above the theme header', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)('/promo'), ['enabled' => 1, 'text' => ['en' => ''], 'link' => 'javascript:alert(1)', 'background' => 'blue'])
        ->assertUnprocessable()->assertJsonValidationErrors(['text.en', 'link', 'background']);
    $this->putJson(($this->url)('/promo'), [
        'enabled' => 1,
        'text' => ['en' => 'Free shipping on orders over $60', 'ar' => 'شحن مجاني للطلبات فوق 60$'],
        'link' => '/pages/shipping-info',
        'background' => '#FFD428',
    ])->assertOk();

    $html = ($this->storefront)();
    expect($html)->toContain('data-store-bar')->toContain('Free shipping on orders over $60')
        ->toContain('href="'.$this->tenantUrl($this->tenant, 'pages/shipping-info').'"');
    preg_match('#href="([^"]+/appearance/ecommet\.css\?v=[0-9a-f]{12})"#', $html, $match);
    expect($this->get(html_entity_decode($match[1]))->getContent())->toContain('--sf-promo-bg: #ffd428;')->toContain('--sf-promo-ink: #121212;')
        ->not->toContain('--nog-primary');

    $this->putJson(($this->url)('/promo'), ['enabled' => 0, 'text' => ['en' => 'Hidden'], 'background' => '#000000'])->assertOk();
    expect(($this->storefront)())->not->toContain('data-store-bar');
});

it('adds, edits, switches, sorts and deletes header links shown in the store bar', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/links/create?placement=header'), $this->ajax)->assertOk()->assertSee('appearance-link-form', false);
    $this->postJson(($this->url)('/links'), appearanceLinkPayload(['label' => ['ar' => ''], 'url' => 'ftp://x']))
        ->assertUnprocessable()->assertJsonValidationErrors(['label.ar', 'url']);

    $this->postJson(($this->url)('/links'), appearanceLinkPayload())->assertCreated()->assertJsonPath('redirect', fn (string $url) => str_contains($url, 'tab=header'));
    $this->postJson(($this->url)('/links'), appearanceLinkPayload(['label' => ['en' => 'Help', 'ar' => 'مساعدة'], 'url' => 'https://help.example.com']))->assertCreated();
    [$track, $help] = $this->tenant->run(fn () => StoreMenuLink::query()->orderBy('sort_order')->get()->all());

    $html = ($this->storefront)();
    expect($html)->toContain('data-store-bar')->toContain('Track order')
        ->toMatch('#<a href="https://help\.example\.com"[^>]*target="_blank" rel="noopener"#');

    $this->get(($this->url)("/links/{$track->id}/edit"), $this->ajax)->assertOk()->assertSee('Track order');
    $this->putJson(($this->url)("/links/{$track->id}"), appearanceLinkPayload(['label' => ['en' => 'Track']]))->assertOk();
    $this->postJson(($this->url)('/links/sort'), ['placement' => 'header', 'ids' => [$help->id, $track->id]])->assertOk();
    $this->patchJson(($this->url)("/links/{$help->id}/active"), ['is_active' => 0])->assertOk();

    $this->tenant->run(function () use ($track, $help): void {
        expect($track->fresh()->label)->toBe('Track')
            ->and($help->fresh()->sort_order)->toBeLessThan($track->fresh()->sort_order)
            ->and($help->fresh()->is_active)->toBeFalse();
    });
    expect(($this->storefront)())->toContain('>Track</a>')->not->toContain('help.example.com');

    $this->deleteJson(($this->url)("/links/{$track->id}"))->assertOk();
    expect(($this->storefront)())->not->toContain('data-store-bar');

    // Header link limit.
    $this->tenant->run(function (): void {
        foreach (range(1, 8) as $index) {
            StoreMenuLink::query()->create(['placement' => MenuLinkPlacement::Header, 'label' => ['en' => "L{$index}", 'ar' => "L{$index}"], 'url' => '/x', 'sort_order' => $index]);
        }
    });
    $this->postJson(($this->url)('/links'), appearanceLinkPayload())->assertUnprocessable()->assertJsonValidationErrors(['label']);
});

it('saves the footer text and custom footer links, shown in the theme footer', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)('/footer'), [
        'about' => ['en' => 'Everyday goods from Riyadh.', 'ar' => 'منتجات يومية من الرياض.'],
        'copyright' => ['en' => '© :year Megz Store', 'ar' => ''],
        'show_social' => '1',
    ])->assertOk();
    $this->postJson(($this->url)('/links'), appearanceLinkPayload(['placement' => 'footer', 'footer_column' => '', 'label' => ['en' => 'Wholesale', 'ar' => 'الجملة'], 'url' => '/pages/wholesale']))
        ->assertUnprocessable()->assertJsonValidationErrors(['footer_column']);
    $this->postJson(($this->url)('/links'), appearanceLinkPayload(['placement' => 'footer', 'footer_column' => 'service', 'label' => ['en' => 'Wholesale', 'ar' => 'الجملة'], 'url' => '/pages/wholesale']))
        ->assertCreated()->assertJsonPath('redirect', fn (string $url) => str_contains($url, 'tab=footer'));

    expect(($this->storefront)())->toContain('Everyday goods from Riyadh.')->toContain('© '.now()->year.' Megz Store')
        ->toContain('Wholesale')->not->toContain('data-store-bar');

    $this->get(($this->url)('?tab=footer'))->assertOk()->assertSee('Wholesale')->assertSee(__('content::store_enums.footer_column.service'));
});

it('manages social links (one per network, own host only) shown as footer icons', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/social/create'), $this->ajax)->assertOk()->assertSee('name="network"', false);
    $this->postJson(($this->url)('/social'), ['network' => 'instagram', 'url' => 'https://evil.example.com/megz', 'is_active' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['url']);
    $this->postJson(($this->url)('/social'), ['network' => 'myspace', 'url' => 'http://instagram.com/megz'])
        ->assertUnprocessable()->assertJsonValidationErrors(['network', 'url']);
    $this->postJson(($this->url)('/social'), ['network' => 'instagram', 'url' => 'https://www.instagram.com/megzstore', 'is_active' => 1])->assertCreated();
    $this->postJson(($this->url)('/social'), ['network' => 'instagram', 'url' => 'https://instagram.com/other', 'is_active' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['network']);
    $this->postJson(($this->url)('/social'), ['network' => 'tiktok', 'url' => 'https://www.tiktok.com/@megz', 'is_active' => 1])->assertCreated();

    $instagram = $this->tenant->run(fn () => StoreSocialLink::query()->where('network', 'instagram')->sole());
    expect(($this->storefront)())->toContain('href="https://www.instagram.com/megzstore"')->toContain('aria-label="Instagram"')->toContain('https://www.tiktok.com/@megz');

    $this->get(($this->url)("/social/{$instagram->id}/edit"), $this->ajax)->assertOk()->assertSee('megzstore');
    $this->putJson(($this->url)("/social/{$instagram->id}"), ['network' => 'instagram', 'url' => 'https://instagram.com/megz', 'is_active' => 1])->assertOk();
    $this->patchJson(($this->url)("/social/{$instagram->id}/active"), ['is_active' => 0])->assertOk();
    expect(($this->storefront)())->not->toContain('instagram.com/megz')->toContain('tiktok.com/@megz');

    // Footer switch hides every icon.
    $this->putJson(($this->url)('/footer'), ['show_social' => '0'])->assertOk();
    expect(($this->storefront)())->not->toContain('tiktok.com/@megz');

    $this->deleteJson(($this->url)("/social/{$instagram->id}"))->assertOk();
    $this->tenant->run(fn () => expect(StoreSocialLink::query()->count())->toBe(1));
});

it('replaces the store logo', function (): void {
    Storage::fake('public');
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->post(($this->url)('/logo'), ['logo' => [UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['logo.0']);
    $this->post(($this->url)('/logo'), ['logo' => [UploadedFile::fake()->image('logo.png', 300, 100)]], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.url', fn (string $url) => str_contains($url, '/tenancy/assets/branding/logo-'));

    expect(($this->setting)(StoreSettingKey::LogoPath))->toStartWith('branding/logo-');
    $this->get(($this->url)())->assertOk()->assertSee('data-logo-preview', false);
});

it('keeps each store to its own links', function (): void {
    $other = $this->createTenant('app-o-'.Str::lower(Str::random(6)));
    $foreign = $other->run(fn (): StoreMenuLink => StoreMenuLink::query()->forceCreate(['id' => 700, 'placement' => 'header', 'label' => json_encode(['en' => 'Foreign']), 'url' => '/x']));
    $social = $other->run(fn (): StoreSocialLink => StoreSocialLink::query()->forceCreate(['id' => 700, 'network' => 'x', 'url' => 'https://x.com/foreign']));
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->getJson(($this->url)("/links/{$foreign->id}/edit"))->assertNotFound();
    $this->putJson(($this->url)("/links/{$foreign->id}"), appearanceLinkPayload())->assertNotFound();
    $this->deleteJson(($this->url)("/links/{$foreign->id}"))->assertNotFound();
    $this->deleteJson(($this->url)("/social/{$social->id}"))->assertNotFound();
    $this->get(($this->url)())->assertOk()->assertDontSee('Foreign')->assertDontSee('x.com/foreign');
    $other->run(fn () => expect(StoreMenuLink::query()->count())->toBe(1));
});
