<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Enums\StoreLayoutScope;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePreviewService;
use App\Modules\Storefront\Services\Layouts\StoreLayoutPreviewService;
use App\Modules\Storefront\Services\Themes\ThemePreviewService;
use App\Shared\Money\Money;
use App\Shared\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Per-request storefront context (scoped singleton): the shopping country, display
 * currency and resolved theme. Every storefront page / partial reads these from here.
 *
 * Country (drives sellable products and prices, decision #65 / #68): the switcher choice
 * in the session → the customer's default address → the visitor country (CDN header) →
 * the store default → the first selling country. Currency is display-only: the session
 * choice, else the country's currency when active, else the base (USD).
 */
class StorefrontContext
{
    public const COUNTRY_SESSION_KEY = 'storefront.country_id';

    public const CURRENCY_SESSION_KEY = 'storefront.currency';

    private ?Country $country = null;

    private bool $countryResolved = false;

    private ?Currency $currency = null;

    private bool $currencyResolved = false;

    /** Vendor theme preview of this browser session (VEN-030, decision #85). */
    private ?ResolvedThemeData $preview = null;

    private ?Request $previewRequest = null;

    public function __construct(
        private readonly StoreCountries $countries,
        private readonly StoreThemeService $themes,
        private readonly CurrencyRepositoryContract $currencies,
        private readonly CustomerAddressRepositoryContract $addresses,
        private readonly MoneyPresenter $money,
        private readonly ThemePreviewService $previews,
        private readonly StoreCurrencyService $storeCurrencies,
        private readonly CustomThemePreviewService $customPreviews,
    ) {}

    public function country(): ?Country
    {
        if ($this->countryResolved) {
            return $this->country;
        }

        $this->countryResolved = true;

        return $this->country = $this->resolveCountry();
    }

    public function countryId(): ?int
    {
        return $this->country()?->id;
    }

    public function theme(): ResolvedThemeData
    {
        // Memo per request object (the scoped context can outlive one request in tests / workers).
        if ($this->previewRequest !== request()) {
            $this->previewRequest = request();
            $this->preview = $this->previews->current(request());

            // A custom Twig theme version preview (decision #121) renders over the store's built-in theme.
            if ($this->preview === null && ($custom = $this->customPreviews->current(request())) !== null) {
                $this->preview = $this->themes->resolveBuiltIn($this->countryId())->withCustom($custom->id, $custom->name.' '.$custom->version);
            }

            // Page-builder draft preview (VEN-032): the home renders the theme the layout was built for, in its country.
            if ($this->preview === null && ($layout = app(StoreLayoutPreviewService::class)->current(request())) !== null
                && $layout->scope === StoreLayoutScope::Home && $layout->theme_id !== null
                && ($theme = $this->themes->activeThemes()->find($layout->theme_id)) instanceof Theme) {
                $this->preview = ResolvedThemeData::fromTheme($theme, true);

                if ($layout->country_id !== null && request()->hasSession()) {
                    request()->session()->put(self::COUNTRY_SESSION_KEY, $layout->country_id);
                    $this->countryResolved = false;
                }
            }

            // A preview link may carry the shopping country (decision #85).
            if ($this->preview !== null && request()->query('tp_country') !== null) {
                $this->countryResolved = false;
            }
        }

        return $this->preview ?? $this->themes->resolve($this->countryId());
    }

    /**
     * The vendor theme preview of this browser session, if any (VEN-030, QA-P8-009).
     */
    public function preview(): ?ResolvedThemeData
    {
        $this->theme();

        return $this->preview;
    }

    public function family(): string
    {
        return $this->theme()->family->value;
    }

    /**
     * Display currency (null = base USD).
     */
    public function currency(): ?Currency
    {
        if ($this->currencyResolved) {
            return $this->currency;
        }

        $this->currencyResolved = true;
        // Session choice when the store still offers it, else the country's currency when the
        // store shows it by default there (VEN-072), else USD.
        $code = request()->hasSession() ? request()->session()->get(self::CURRENCY_SESSION_KEY) : null;
        $currency = is_string($code) && $code !== '' ? $this->currencies->findByCode(strtoupper($code)) : null;
        $currency = $currency !== null && $this->storeCurrencies->isAvailable($currency) ? $currency : null;

        if ($currency === null) {
            $code = $this->country()?->currency_code;
            $currency = is_string($code) && $code !== '' ? $this->currencies->findByCode(strtoupper($code)) : null;
            $currency = $currency !== null && $this->storeCurrencies->isCountryDefault($currency) ? $currency : null;
        }

        return $this->currency = $currency !== null && $currency->is_active && ! $currency->isBase() ? $currency : null;
    }

    public function currencyCode(): string
    {
        return $this->currency()->code ?? Money::BASE_CURRENCY;
    }

    /**
     * USD amount formatted in the display currency (conversion is display-only).
     */
    public function formatMoney(Money|int $amount): string
    {
        $this->money->useDisplayCurrency($this->currency()?->code);
        $presented = $this->money->present($amount);

        return $presented['display']['formatted'] ?? $presented['formatted'];
    }

    /**
     * Switcher choices (validated by the caller): kept in the session.
     */
    public function remember(?int $countryId, ?string $currency): void
    {
        if (! request()->hasSession()) {
            return;
        }

        if ($countryId !== null) {
            request()->session()->put(self::COUNTRY_SESSION_KEY, $countryId);
        }

        if ($currency !== null) {
            request()->session()->put(self::CURRENCY_SESSION_KEY, strtoupper($currency));
        }

        $this->countryResolved = false;
        $this->currencyResolved = false;
    }

    /**
     * JSON API request (Store API catalog, mobile app): shopping country from `X-Country` /
     * `X-Country-Code` (ISO2, only a country the store sells in) and display currency from
     * `X-Currency` (only one the store offers). Missing / unknown values fall back to the
     * normal resolution (customer default address → visitor → store default).
     */
    public function useApiPreferences(?string $countryIso2, ?string $currencyCode): void
    {
        $this->countryResolved = false;
        $this->currencyResolved = false;

        if (is_string($countryIso2) && preg_match('/^[A-Za-z]{2}$/', $countryIso2) === 1 && ($country = $this->countries->findByIso2(strtoupper($countryIso2))) !== null) {
            $this->country = $country;
            $this->countryResolved = true;
        }

        if (is_string($currencyCode) && preg_match('/^[A-Za-z]{3}$/', $currencyCode) === 1) {
            $currency = $this->currencies->findByCode(strtoupper($currencyCode));

            if ($currency !== null && ($currency->isBase() || ($currency->is_active && $this->storeCurrencies->isAvailable($currency)))) {
                $this->currency = $currency->isBase() ? null : $currency;
                $this->currencyResolved = true;
            }
        }

        $this->money->useDisplayCurrency($this->currency()?->code);
    }

    /**
     * Data island for the storefront JS (`<script type="application/json" id="sf-context">`).
     *
     * @return array<string, mixed>
     */
    public function toClient(): array
    {
        $country = $this->country();

        return [
            'locale' => app()->getLocale(),
            'dir' => Locale::direction(),
            'country' => $country !== null ? ['id' => $country->id, 'iso2' => strtolower($country->iso2), 'name' => $country->name] : null,
            'currency' => $this->currencyCode(),
            'theme' => $this->theme()->toArray(),
        ];
    }

    private function resolveCountry(): ?Country
    {
        $session = request()->hasSession() ? request()->session()->get(self::COUNTRY_SESSION_KEY) : null;

        if (is_numeric($session) && ($country = $this->countries->find((int) $session)) !== null) {
            return $country;
        }

        $customer = Auth::guard('customer')->user();
        $address = $customer instanceof Customer ? $this->addresses->defaultFor($customer->id) : null;

        if ($address !== null && ($country = $this->countries->find((int) $address->country_id)) !== null) {
            return $country;
        }

        if (($country = $this->countries->findByIso2(Locale::visitorCountry())) !== null) {
            return $country;
        }

        $tenant = tenancy()->initialized ? tenant() : null;
        $default = $tenant instanceof Tenant ? $tenant->default_country_id : null;

        if ($default !== null && ($country = $this->countries->find((int) $default)) !== null) {
            return $country;
        }

        return $this->countries->available()->first();
    }
}
