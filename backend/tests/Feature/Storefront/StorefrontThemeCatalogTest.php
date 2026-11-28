<?php

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Models\ThemePart;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Storefront side of the theme catalogue (decisions #119 / #120): the store setting holds
| theme ids (slug values migrated), resolution falls back to the platform default for an
| inactive theme or a country the theme is not allowed in, the home renders the theme's
| admin section layout and the default template parts are applied.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup('catalogue');
    tenancy()->end();
    $this->theme = fn (string $slug): Theme => Theme::query()->where('slug', $slug)->firstOrFail();
    $this->resolve = fn (?int $country): string => $this->shop->tenant->run(fn (): string => app(StoreThemeService::class)->resolve($country)->slug);
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

it('stores theme ids and falls back to the platform default for inactive or disallowed themes', function (): void {
    $elora = ($this->theme)('elora-3');
    $souqify = ($this->theme)('souqify-2');

    $this->shop->tenant->run(function () use ($elora, $souqify): void {
        app(StoreThemeService::class)->activate((int) $elora->id);
        app(StoreThemeService::class)->activate('souqify-2', [$this->shop->ae->id]);
        expect(app(StoreSettingRepositoryContract::class)->get(StoreSettingKey::StoreTheme))
            ->toBe(['default' => $elora->id, 'countries' => [(string) $this->shop->ae->id => $souqify->id]]);
    });

    expect(($this->resolve)($this->shop->sa->id))->toBe('elora-3')
        ->and(($this->resolve)($this->shop->ae->id))->toBe('souqify-2');

    // Souqify 2 only allowed in SA now: AE falls back to the store default.
    $souqify->countries()->sync([$this->shop->sa->id]);
    expect(($this->resolve)($this->shop->ae->id))->toBe('elora-3');

    // Store default deactivated: the platform default.
    $elora->update(['is_active' => false]);
    expect(($this->resolve)($this->shop->sa->id))->toBe('ecommet');

    ($this->theme)('souqify-1')->update(['is_default' => true]);
    ($this->theme)('ecommet')->update(['is_default' => false]);
    expect(($this->resolve)($this->shop->sa->id))->toBe('souqify-1');

    // The store's saved choice is untouched.
    $elora->update(['is_active' => true]);
    expect(($this->resolve)($this->shop->sa->id))->toBe('elora-3');
});

it('refuses a country the theme is not allowed in and resets to the platform default', function (): void {
    $theme = ($this->theme)('elora-4');
    $theme->countries()->sync([$this->shop->sa->id]);

    $this->shop->tenant->run(function () use ($theme): void {
        expect(fn () => app(StoreThemeService::class)->activate((int) $theme->id, [$this->shop->ae->id]))->toThrow(ValidationException::class);

        app(StoreThemeService::class)->activate((int) $theme->id, [$this->shop->sa->id]);
        app(StoreThemeService::class)->resetToPlatformDefault();
        expect(app(StoreThemeService::class)->config())->toBe(['default' => null, 'countries' => [], 'saved' => true]);
    });

    expect(($this->resolve)($this->shop->sa->id))->toBe('ecommet');
});

it('migrates slug values of the store theme setting to theme ids', function (): void {
    $this->shop->tenant->run(function (): void {
        app(StoreSettingRepositoryContract::class)->put(StoreSettingKey::StoreTheme, ['default' => 'elora-2', 'countries' => [(string) $this->shop->sa->id => 'souqify-5', '77' => 'gone-theme']]);

        // Leftover slugs are still read before the migration ran.
        expect(app(StoreThemeService::class)->config()['default'])->toBe(($this->theme)('elora-2')->id);

        (require base_path('app/Modules/Storefront/Database/migrations/tenant/2026_12_24_000100_convert_store_theme_to_theme_ids.php'))->up();

        expect(app(StoreSettingRepositoryContract::class)->get(StoreSettingKey::StoreTheme))->toBe([
            'default' => ($this->theme)('elora-2')->id,
            'countries' => [(string) $this->shop->sa->id => ($this->theme)('souqify-5')->id],
        ]);
    });
});

it('renders the home in the admin section order and applies the default template parts', function (): void {
    $theme = ($this->theme)('ecommet');
    $theme->update(['home_sections' => [['key' => 'hero', 'enabled' => true], ['key' => 'best-grid', 'enabled' => false]]]);
    $part = fn (ThemePartType $type, array $settings, bool $default = true): ThemePart => ThemePart::query()->create(['theme_id' => $theme->id, 'type' => $type, 'name' => $type->value, 'settings' => $settings, 'is_active' => true, 'is_default' => $default]);
    $part(ThemePartType::Announcement, ['message' => ['en' => 'Free delivery this week'], 'link' => '/new-in', 'tone' => 'brand']);
    $part(ThemePartType::Announcement, ['message' => ['en' => 'Not the default'], 'tone' => 'dark'], false);
    $part(ThemePartType::Header, ['sticky' => true]);
    $part(ThemePartType::ProductCard, ['show_rating' => false, 'show_hover_image' => true]);
    $part(ThemePartType::Footer, ['copyright' => ['en' => '© :year Catalogue shop']]);

    $this->withSession($this->sa)->get(($this->url)('/'))
        ->assertOk()
        ->assertSee('data-sf-announcement="brand"', false)
        ->assertSee('Free delivery this week')
        ->assertDontSee('Not the default')
        ->assertSee('data-sf-sticky-header', false)
        ->assertSee('data-card-rating="off"', false)
        ->assertDontSee('data-card-hover="off"', false)
        ->assertSee('© '.now()->year.' Catalogue shop')
        ->assertDontSee('id="sf-best-grid"', false);

    $this->getJson(($this->url)('/store-api/v1/home'))->assertOk()
        ->assertJsonPath('data.layout.0', 'hero')
        ->assertJsonCount(count(ThemeSectionLibrary::keys(ThemeFamily::Ecommet, 1)) - 1, 'data.layout');

    // An inactive default part is not applied.
    ThemePart::query()->where('type', 'announcement')->where('is_default', true)->update(['is_active' => false]);
    $this->app->forgetScopedInstances(); // a new request (parts are memoised per request)
    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertDontSee('data-sf-announcement', false);
});
