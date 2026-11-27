<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The whitelisted theme functions / filters (documented in docs/themes/README.md):
 *
 *   url('product', {id: 12, slug: 'tee'})   asset('css/theme.css')   script('js/theme.js')
 *   money(1999) / 1999|money                 t('home.title') / 'home.title'|t
 *   st('cart.title')                         setting('accent')        head() scripts()
 *   product.variants|json   (JSON safe inside <script type="application/json"> and attributes)
 *   csrf_field() csrf_token()
 *
 * Only `head`, `scripts`, `script` and `csrf_field` return HTML (ours); everything else
 * is a plain string the sandbox escapes.
 */
final class ThemeExtension extends AbstractExtension
{
    /**
     * Theme URL names => storefront route names.
     *
     * @var array<string, string>
     */
    public const ROUTES = [
        'home' => 'storefront.home',
        'search' => 'storefront.search',
        'categories' => 'storefront.categories.index',
        'category' => 'storefront.category',
        'product' => 'storefront.product',
        'page' => 'storefront.pages.show',
        'best_sellers' => 'storefront.best-sellers',
        'new_in' => 'storefront.new-in',
        'top_rated' => 'storefront.top-rated',
        'offers' => 'storefront.offers',
        'cart' => 'storefront.cart',
        'checkout' => 'storefront.checkout',
        'account' => 'storefront.orders.index',
        'orders' => 'storefront.orders.index',
        'wishlist' => 'storefront.favorites',
        'returns' => 'storefront.returns.index',
        'profile' => 'storefront.account.profile',
        'addresses' => 'storefront.account.addresses',
        'support' => 'storefront.support.index',
        'login' => 'storefront.account.login',
        'register' => 'storefront.account.register',
        'logout' => 'storefront.account.logout',
        'preferences' => 'storefront.preferences',
        'newsletter' => 'storefront.newsletter.store',
    ];

    public function __construct(private ThemeRuntime $runtime) {}

    public function use(ThemeRuntime $runtime): void
    {
        $this->runtime = $runtime;
    }

    public function runtime(): ThemeRuntime
    {
        return $this->runtime;
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', $this->url(...)),
            new TwigFunction('asset', $this->asset(...)),
            new TwigFunction('script', $this->script(...), ['is_safe' => ['html']]),
            new TwigFunction('money', $this->money(...)),
            new TwigFunction('t', $this->translate(...)),
            new TwigFunction('st', $this->staticText(...)),
            new TwigFunction('setting', $this->setting(...)),
            new TwigFunction('head', fn (): Markup => new Markup(($this->runtime->head)(), 'UTF-8'), ['is_safe' => ['html']]),
            new TwigFunction('scripts', fn (): Markup => new Markup(($this->runtime->scripts)(), 'UTF-8'), ['is_safe' => ['html']]),
            new TwigFunction('csrf_token', fn (): string => $this->runtime->csrfToken),
            new TwigFunction('csrf_field', fn (): Markup => new Markup('<input type="hidden" name="_token" value="'.e($this->runtime->csrfToken).'">', 'UTF-8'), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('t', $this->translate(...)),
            new TwigFilter('json', static fn (mixed $value): Markup => new Markup((string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), 'UTF-8'), ['is_safe' => ['html', 'js']]),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function url(string $name, array $params = []): string
    {
        if (! isset(self::ROUTES[$name])) {
            $this->runtime->warn(sprintf('url(): unknown page "%s".', $name));

            return '#';
        }

        $params = array_filter($params, static fn (mixed $value): bool => is_scalar($value));

        return ($this->runtime->url)($name, $params) ?? '#';
    }

    public function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! isset($this->runtime->assets[$path])) {
            $this->runtime->warn(sprintf('asset(): "assets/%s" is not in the package.', $path));

            return '';
        }

        return $this->runtime->assetBase.'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function script(string $path): Markup
    {
        if (! str_ends_with(strtolower($path), '.js')) {
            $this->runtime->warn(sprintf('script(): "%s" is not a .js file.', $path));

            return new Markup('', 'UTF-8');
        }

        $url = $this->asset($path);

        return new Markup($url === '' ? '' : '<script src="'.e($url).'" nonce="'.e($this->runtime->nonce).'" defer></script>', 'UTF-8');
    }

    public function money(mixed $minor): string
    {
        return is_numeric($minor) ? ($this->runtime->money)((int) $minor) : '';
    }

    /**
     * Theme string from locales/<locale>.json (dot keys), English fallback, else the key.
     *
     * @param  array<string, mixed>  $params  replaces ":name" placeholders
     */
    public function translate(string $key, array $params = []): string
    {
        $value = data_get($this->runtime->strings[$this->runtime->locale] ?? [], $key) ?? data_get($this->runtime->strings['en'] ?? [], $key);

        if (! is_string($value)) {
            $this->runtime->warn(sprintf('t(): missing string "%s".', $key));

            return $key;
        }

        foreach ($params as $name => $replace) {
            if (is_scalar($replace)) {
                $value = str_replace(':'.$name, (string) $replace, $value);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function staticText(string $key, array $params = []): string
    {
        return ($this->runtime->staticText)($key, array_filter($params, static fn (mixed $value): bool => is_scalar($value)));
    }

    public function setting(string $id): mixed
    {
        return $this->runtime->settings[$id] ?? null;
    }
}
