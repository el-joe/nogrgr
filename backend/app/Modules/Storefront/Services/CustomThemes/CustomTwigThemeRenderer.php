<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Storefront\DTOs\SeoData;
use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Services\Checkout\StorefrontCart;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Services\Tracking\TrackingPixelService;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeCsp;
use App\Modules\Storefront\Support\CustomThemes\ThemeManifest;
use App\Modules\Storefront\Support\CustomThemes\ThemeUrls;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeExtension;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeRuntime;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeTwigFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;
use Twig\Environment;

/**
 * Renders storefront pages with a store's custom Twig theme (SF-ALL-006, decision #121):
 * ThemeViewModels turns the controller data into plain arrays, ThemeShell adds the
 * globals, and the version's sandboxed Twig environment (compiled cache per tenant /
 * version) renders the page template. Callers fall back to the built-in theme on any
 * exception (CustomThemeView, StoreErrorPage).
 */
class CustomTwigThemeRenderer
{
    public const RUNTIME_ENTRY = 'resources/js/storefront/custom-theme.js';

    /**
     * @var array<int, array{0: Environment, 1: ThemeRuntime}>
     */
    private array $environments = [];

    public function __construct(
        private readonly CustomThemeRepositoryContract $themes,
        private readonly CustomThemePackageStore $store,
        private readonly ThemeViewModels $models,
        private readonly ThemeShell $shell,
        private readonly CustomThemeCsp $csp,
        private readonly StorefrontContext $context,
        private readonly TrackingPixelService $tracking,
    ) {}

    /**
     * Tracking pixels config of this request (null = none on), see prepareTracking().
     *
     * @var array{pixels: array<string, string>, cookie: string, reload_on_consent?: bool}|null
     */
    private ?array $trackingConfig = null;

    public function version(int $versionId): ?CustomThemeVersion
    {
        $version = $this->themes->findVersion($versionId);

        return $version !== null && $version->status->canPreview() && (! tenancy()->initialized || (string) $version->tenant_id === (string) tenant()?->getTenantKey()) ? $version : null;
    }

    /**
     * The page HTML, or null when this view always uses the built-in theme.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(CustomThemeVersion $version, string $view, array $data): ?string
    {
        $page = $this->models->page($view, $data);

        if ($page === null) {
            return null;
        }

        $seo = $data['seo'] ?? null;

        return $this->renderTemplate($version, $page['template'], $page['vars'], $seo instanceof SeoData ? $seo : null);
    }

    public function renderError(CustomThemeVersion $version, int $code): string
    {
        $template = $code === 404 ? CustomThemeTemplate::NotFound : CustomThemeTemplate::ServerError;

        return $this->renderTemplate($version, $template, $this->models->error($code) + ['seo' => ['title' => (string) $this->models->error($code)['error']['title'], 'description' => null, 'canonical' => null]], null);
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function renderTemplate(CustomThemeVersion $version, CustomThemeTemplate $template, array $vars, ?SeoData $seo): string
    {
        [$twig, $runtime] = $this->environment($version, $seo, (string) ($vars['seo']['title'] ?? ''));
        $settings = $runtime->settings;
        $context = $this->shell->current(['name' => $version->name, 'version' => $version->version], $settings, $this->cartCount($vars));

        $this->csp->markUsed();
        $this->prepareTracking();

        return ThemeTwigFactory::render($twig, $template->value.'.twig', ThemeViewModels::plain($context + $vars + ['template' => $template->value]));
    }

    /**
     * @return array{0: Environment, 1: ThemeRuntime}
     */
    private function environment(CustomThemeVersion $version, ?SeoData $seo, string $title): array
    {
        $contents = $this->store->contents($version);
        $templates = $this->store->templates($version);

        if ($templates === []) {
            throw new RuntimeException(sprintf('Custom theme version %d has no templates on disk.', $version->id));
        }

        $manifest = $version->manifest;
        $values = ThemeManifest::settings($manifest, $version->theme->settings);
        $strings = [];

        foreach (['en', 'ar'] as $code) {
            $decoded = isset($contents['locales/'.$code.'.json']) ? json_decode($contents['locales/'.$code.'.json'], true) : null;
            $strings[$code] = is_array($decoded) ? $decoded : [];
        }

        $assets = [];

        foreach (array_keys($version->files()) as $path) {
            if (str_starts_with($path, 'assets/')) {
                $assets[substr($path, strlen('assets/'))] = true;
            }
        }

        $runtime = new ThemeRuntime(
            assets: $assets,
            assetBase: rtrim(route('storefront.theme-assets', ['version' => $version->id, 'path' => 'x']), 'x/'),
            strings: $strings,
            locale: app()->getLocale(),
            nonce: $this->csp->nonce(),
            settings: ThemeManifest::templateValues($manifest, $values),
            csrfToken: (string) csrf_token(),
            url: static fn (string $name, array $params): ?string => ThemeUrls::make($name, $params),
            money: fn (int $minor): string => $this->context->formatMoney($minor),
            head: fn (): string => $this->head($version, $seo, $title, ThemeManifest::fontEntries($manifest, $values)),
            scripts: fn (): string => $this->scripts(),
            staticText: static fn (string $key, array $params): string => __st($key, $params),
        );

        if (isset($this->environments[$version->id])) {
            $this->environments[$version->id][0]->getExtension(ThemeExtension::class)->use($runtime);

            return [$this->environments[$version->id][0], $runtime];
        }

        $twig = ThemeTwigFactory::make($templates, $runtime, $this->store->compiledPath($version));

        return $this->environments[$version->id] = [$twig, $runtime];
    }

    /**
     * @param  list<string>  $fonts
     */
    private function head(CustomThemeVersion $version, ?SeoData $seo, string $title, array $fonts): string
    {
        $nonce = $this->csp->nonce();
        Vite::useCspNonce($nonce);
        $island = $this->context->toClient() + ['routes' => array_filter([
            'cart_items' => Route::has('store-api.v1.cart.items.store') ? route('store-api.v1.cart.items.store') : null,
            'cart' => route('storefront.cart'),
            'search' => route('storefront.search'),
            'autocomplete' => route('storefront.search.autocomplete'),
            'favorite_toggle' => Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null,
            'login' => Route::has('storefront.account.login') ? route('storefront.account.login') : null,
        ])];
        $fullTitle = $seo->title ?? $title;

        $html = [
            '<meta http-equiv="Content-Security-Policy" content="'.e($this->csp->policy(true)).'">',
            '<meta name="csrf-token" content="'.e((string) csrf_token()).'">',
            '<meta name="nogrgr-theme" content="'.e($version->name.' '.$version->version).'" data-nt-head>',
            '<title>'.e($fullTitle !== '' ? $fullTitle : (string) tenant('name')).'</title>',
            $seo !== null ? Blade::render('<x-sf::seo :seo="$seo" />', ['seo' => $seo]) : '',
            '<script type="application/json" id="sf-context">'.json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>',
        ];

        if ($fonts !== []) {
            $html[] = (string) $this->vite($fonts);
        }

        return implode("\n", array_filter($html));
    }

    private function scripts(): string
    {
        Vite::useCspNonce($this->csp->nonce());
        $html = $this->trackingHtml().(string) $this->vite([self::RUNTIME_ENTRY]);
        $preview = $this->context->preview();

        if ($preview !== null && $preview->isCustom()) {
            $exit = e(request()->fullUrlWithQuery(['theme_preview' => 'off', 'ct_preview' => null, 'ct_expires' => null, 'ct_signature' => null]));
            $html .= '<div role="region" aria-label="'.e(__('content::vendor_themes.preview_bar.label')).'" data-theme-preview-bar style="position:fixed;inset-inline:0;bottom:0;z-index:2147483000;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;padding:8px 16px;background:#171717;color:#fff;font:500 13px/20px system-ui,sans-serif">'
                .'<span>'.e(__('content::vendor_themes.preview_bar.text', ['theme' => (string) $preview->name])).'</span>'
                .'<a href="'.$exit.'" data-theme-preview-exit style="color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:999px;padding:2px 12px;text-decoration:none">'.e(__('content::vendor_themes.preview_bar.exit')).'</a></div>';
        }

        return $html;
    }

    /**
     * Store tracking pixels on custom theme pages (decision #91 + #121): the #sf-tracking
     * island + consent notice like the built-in themes; the CSP only opens the pixel hosts
     * once the shopper accepted (first-party consent cookie). On "Accept" the runtime reloads
     * the page so the pixels load under the widened policy.
     */
    private function prepareTracking(): void
    {
        $config = $this->tracking->storefrontConfig();
        $this->trackingConfig = $config !== null ? $config + ['reload_on_consent' => true] : null;

        if ($config !== null && request()->cookie(TrackingPixelService::CONSENT_COOKIE) === 'granted') {
            $this->csp->allowTracking(array_keys($config['pixels']));
        }
    }

    private function trackingHtml(): string
    {
        if ($this->trackingConfig === null) {
            return '';
        }

        try {
            return view('storefront::custom-themes.tracking-consent', ['config' => $this->trackingConfig])->render();
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }
    }

    /**
     * @param  list<string>  $entries
     */
    private function vite(array $entries): HtmlString|string
    {
        try {
            return app(\Illuminate\Foundation\Vite::class)($entries);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function cartCount(array $vars): int
    {
        if (isset($vars['cart_page']['items_count'])) {
            return (int) $vars['cart_page']['items_count'];
        }

        try {
            return (int) (app(StorefrontCart::class)->data(request())['items_count'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Drop the compiled templates and in-memory environment of a version (publish / delete).
     */
    public function invalidate(CustomThemeVersion $version): void
    {
        unset($this->environments[$version->id]);
        $this->store->clearCompiled($version);
    }
}
