<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Identity\Models\Customer;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\StorefrontShell;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StorefrontTheme;
use App\Shared\Support\Locale;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Globals every custom theme template receives (`store`, `menus`, `customer`, `cart`,
 * `request`, `theme`, `settings`), built from the same read models as the Blade shells.
 * `build()` is pure (also fed by ThemeFixtures for the smoke render and the docs).
 */
class ThemeShell
{
    public function __construct(
        private readonly StorefrontContext $context,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontShell $shell,
        private readonly StorefrontAppearance $appearance,
        private readonly ThemeViewModels $models,
    ) {}

    /**
     * @param  array<string, mixed>  $theme  {name, version}
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function current(array $theme, array $settings, int $cartCount): array
    {
        $country = $this->context->country();
        $customer = Auth::guard('customer')->user();

        return $this->build([
            'name' => StorefrontTheme::storeName(),
            'logo_url' => StorefrontTheme::logoUrl(),
            'locale' => app()->getLocale(),
            'dir' => Locale::direction(),
            'locales' => array_map(static fn (string $code, array $locale): array => ['code' => $code, 'name' => (string) ($locale['native'] ?? $code), 'current' => $code === app()->getLocale()], array_keys(Locale::enabled()), Locale::enabled()),
            'currency' => $this->context->currencyCode(),
            'currencies' => $this->safe(fn (): array => app(StoreCurrencyService::class)->switcherCurrencies()->map(static fn (object $currency): array => ['code' => (string) $currency->code, 'name' => (string) $currency->name])->values()->all()),
            'country' => $country !== null ? ['id' => (int) $country->id, 'iso2' => strtolower((string) $country->iso2), 'name' => (string) $country->name] : null,
            'countries' => $this->safe(fn (): array => app(StoreCountries::class)->available()->map(static fn (object $item): array => ['id' => (int) $item->id, 'iso2' => strtolower((string) $item->iso2), 'name' => (string) $item->name])->values()->all()),
            'about' => $this->appearance->aboutText(),
            'copyright' => $this->appearance->copyright(),
            'menu_categories' => $this->models->tree($this->safe(fn (): array => $this->categories->menu())),
            'header_links' => $this->safe(fn (): array => $this->appearance->headerLinks()),
            'footer' => $this->safe(fn (): array => $this->shell->footerLinks(), ['columns' => [], 'legal' => []]),
            'social' => $this->safe(fn (): array => $this->appearance->socialLinks()),
            'customer' => $customer instanceof Customer ? ['signed_in' => true, 'name' => (string) $customer->name] : ['signed_in' => false, 'name' => null],
            'cart_count' => $cartCount,
            'path' => '/'.ltrim(request()->path(), '/'),
            'url' => request()->url(),
            'query' => is_string(request()->query('q')) ? mb_substr((string) request()->query('q'), 0, 100) : null,
            'theme' => $theme,
            'settings' => $settings,
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function build(array $raw): array
    {
        $footer = is_array($raw['footer'] ?? null) ? $raw['footer'] : [];
        $link = static fn (mixed $item): array => is_array($item) ? ['title' => (string) ($item['title'] ?? ''), 'url' => (string) ($item['url'] ?? ''), 'external' => (bool) ($item['external'] ?? false)] : [];

        return [
            'store' => [
                'name' => (string) $raw['name'],
                'logo_url' => $raw['logo_url'] ?? null,
                'home_url' => route('storefront.home'),
                'locale' => (string) $raw['locale'],
                'dir' => (string) $raw['dir'],
                'locales' => array_values((array) ($raw['locales'] ?? [])),
                'currency' => (string) $raw['currency'],
                'currencies' => array_values((array) ($raw['currencies'] ?? [])),
                'country' => $raw['country'] ?? null,
                'countries' => array_values((array) ($raw['countries'] ?? [])),
                'about' => $raw['about'] ?? null,
                'copyright' => $raw['copyright'] ?? null,
                'year' => (int) ($raw['year'] ?? date('Y')),
            ],
            'menus' => [
                'categories' => array_values((array) ($raw['menu_categories'] ?? [])),
                'header' => array_map($link, array_values((array) ($raw['header_links'] ?? []))),
                'footer' => array_map(static fn (mixed $column): array => array_map($link, array_values((array) $column)), (array) ($footer['columns'] ?? [])),
                'legal' => array_map($link, array_values((array) ($footer['legal'] ?? []))),
                'social' => array_values(array_map(static fn (mixed $item): array => is_array($item) ? ['network' => (string) ($item['network'] ?? ''), 'url' => (string) ($item['url'] ?? ''), 'label' => (string) ($item['label'] ?? $item['network'] ?? '')] : [], (array) ($raw['social'] ?? []))),
            ],
            'customer' => $raw['customer'],
            'cart' => ['count' => (int) ($raw['cart_count'] ?? 0), 'url' => route('storefront.cart')],
            'request' => ['path' => (string) $raw['path'], 'url' => (string) $raw['url'], 'query' => $raw['query'] ?? null],
            'theme' => $raw['theme'],
            'settings' => $raw['settings'],
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @param  T  $default
     * @return T
     */
    private function safe(callable $read, mixed $default = []): mixed
    {
        try {
            return $read();
        } catch (Throwable) {
            return $default;
        }
    }
}
