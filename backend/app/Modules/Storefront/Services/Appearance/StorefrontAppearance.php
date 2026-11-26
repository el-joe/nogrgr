<?php

namespace App\Modules\Storefront\Services\Appearance;

use App\Modules\Storefront\DTOs\Appearance\AppearanceColorsData;
use App\Modules\Storefront\DTOs\Appearance\FooterSettingsData;
use App\Modules\Storefront\DTOs\Appearance\PromoBarData;
use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Models\StoreSocialLink;
use App\Modules\Storefront\Repositories\Contracts\StoreMenuLinkRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreSocialLinkRepositoryContract;
use App\Shared\Support\Locale;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Storefront reads of the store's appearance (scoped per request): promo bar, store-bar
 * header links, footer text / custom links / social icons, chosen font bundles and the
 * appearance stylesheet URL. Missing tables (store not migrated yet) read as defaults.
 */
class StorefrontAppearance
{
    /** Request + store the memoised values belong to (a long-lived container serves many). */
    private ?string $scope = null;

    private ?AppearanceColorsData $colors = null;

    private ?PromoBarData $promo = null;

    private ?FooterSettingsData $footer = null;

    /**
     * @var array<string, list<array{title: string, url: string, external: bool}>>|null
     */
    private ?array $menu = null;

    /**
     * @var list<array{network: string, label: string, icon: string, url: string}>|null
     */
    private ?array $social = null;

    public function __construct(
        private readonly StoreAppearanceService $appearance,
        private readonly StoreMenuLinkRepositoryContract $links,
        private readonly StoreSocialLinkRepositoryContract $socialLinks,
        private readonly AppearanceStylesheet $stylesheet,
    ) {}

    public function colors(): AppearanceColorsData
    {
        $this->refreshScope();

        return $this->colors ??= $this->safely(fn (): AppearanceColorsData => $this->appearance->colors(), new AppearanceColorsData);
    }

    public function promo(): PromoBarData
    {
        $this->refreshScope();

        return $this->promo ??= $this->safely(fn (): PromoBarData => $this->appearance->promo(), new PromoBarData);
    }

    public function footer(): FooterSettingsData
    {
        $this->refreshScope();

        return $this->footer ??= $this->safely(fn (): FooterSettingsData => $this->appearance->footer(), new FooterSettingsData);
    }

    /**
     * Visible promo line for the current language, or null.
     *
     * @return array{text: string, url: string|null, external: bool}|null
     */
    public function promoBar(): ?array
    {
        $promo = $this->promo();

        if (! $promo->isVisible()) {
            return null;
        }

        return [
            'text' => (string) $promo->textFor(app()->getLocale()),
            'url' => $promo->link !== null ? $this->href($promo->link) : null,
            'external' => $promo->link !== null && $this->isExternal($promo->link),
        ];
    }

    /**
     * @return list<array{title: string, url: string, external: bool}>
     */
    public function headerLinks(): array
    {
        return $this->menu()['header'];
    }

    /**
     * Custom footer links per column (company / service / help).
     *
     * @return array<string, list<array{title: string, url: string, external: bool}>>
     */
    public function footerLinks(): array
    {
        $menu = $this->menu();
        unset($menu['header']);

        return array_filter($menu);
    }

    /**
     * @return list<array{network: string, label: string, icon: string, url: string}>
     */
    public function socialLinks(): array
    {
        if (! $this->footer()->showSocial) {
            return [];
        }

        $this->refreshScope();

        return $this->social ??= $this->safely(fn (): array => $this->socialLinks->ordered(activeOnly: true)
            ->map(static fn (StoreSocialLink $link): array => [
                'network' => $link->network->value,
                'label' => $link->network->label(),
                'icon' => $link->network->icon(),
                'url' => $link->url,
            ])->values()->all(), []);
    }

    public function aboutText(): ?string
    {
        return $this->footer()->aboutFor(app()->getLocale());
    }

    public function copyright(): ?string
    {
        $line = $this->footer()->copyrightFor(app()->getLocale());

        return $line !== null ? str_replace(':year', (string) now()->year, $line) : null;
    }

    /**
     * Vite inputs of the chosen fonts for the page direction.
     *
     * @return list<string>
     */
    public function fontEntries(): array
    {
        $font = Locale::direction() === 'rtl' ? $this->colors()->fontRtl : $this->colors()->fontLtr;

        return $font !== null ? [$font->cssEntry()] : [];
    }

    public function css(string $family): string
    {
        return $this->stylesheet->build($family, $this->colors(), $this->promo());
    }

    /**
     * Versioned URL of the appearance stylesheet, or null when the store uses the theme
     * defaults (no colour / font change and no promo bar).
     */
    public function stylesheetUrl(string $family): ?string
    {
        $colors = $this->colors();

        if (($colors->isDefault() && ! $this->promo()->isVisible()) || ! Route::has('storefront.appearance.css')) {
            return null;
        }

        return route('storefront.appearance.css', ['family' => $family, 'v' => substr(sha1($this->css($family)), 0, 12)]);
    }

    /**
     * @return array<string, list<array{title: string, url: string, external: bool}>>
     */
    private function menu(): array
    {
        $this->refreshScope();

        if ($this->menu !== null) {
            return $this->menu;
        }

        $menu = ['header' => []] + array_fill_keys(FooterColumn::values(), []);

        $rows = $this->safely(fn () => $this->links->query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(), collect());

        foreach ($rows as $link) {
            /** @var StoreMenuLink $link */
            $item = ['title' => (string) $link->label, 'url' => $this->href($link->url), 'external' => $link->isExternal()];

            if ($link->placement === MenuLinkPlacement::Header) {
                $menu['header'][] = $item;
            } elseif ($link->footer_column !== null) {
                $menu[$link->footer_column->value][] = $item;
            }
        }

        return $this->menu = $menu;
    }

    private function refreshScope(): void
    {
        $scope = spl_object_id(request()).'|'.(tenancy()->initialized ? (string) tenant()?->getTenantKey() : '');

        if ($scope !== $this->scope) {
            $this->scope = $scope;
            $this->colors = $this->promo = $this->footer = null;
            $this->menu = $this->social = null;
        }
    }

    private function href(string $url): string
    {
        return $this->isExternal($url) ? $url : url($url);
    }

    private function isExternal(string $url): bool
    {
        return preg_match('#^https?://#i', $url) === 1;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @param  T  $fallback
     * @return T
     */
    private function safely(callable $read, mixed $fallback): mixed
    {
        if (! tenancy()->initialized) {
            return $fallback;
        }

        try {
            return $read();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
