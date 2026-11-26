<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
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
        $code = request()->hasSession() ? request()->session()->get(self::CURRENCY_SESSION_KEY) : null;
        $code = is_string($code) && $code !== '' ? $code : $this->country()?->currency_code;
        $currency = is_string($code) && $code !== '' ? $this->currencies->findByCode(strtoupper($code)) : null;

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
