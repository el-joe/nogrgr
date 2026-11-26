<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\Enums\AppUpdateState;
use App\Modules\Api\Enums\MobileFeature;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Modules\Notifications\Support\Push\FcmClient;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreGeneralSettingsService;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Services\Appearance\StoreAppearanceService;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Money\Money;
use App\Shared\Support\Locale;
use Illuminate\Support\Facades\Storage;

/**
 * App start-up data of the current store (APP-001 / APP-002 / APP-003): store identity
 * and branding (theme family + variant, brand colour, fonts, logo, splash), languages,
 * display currencies (charges are always USD, decision #87), shipping countries, feature
 * flags, push availability and the update / maintenance decision for the app version.
 * Everything is read from the store / platform DB (no duplicated central data, #65);
 * static texts are a separate call keyed by `texts.version`.
 */
class MobileBootstrapService
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $resolved = [];

    public function __construct(
        private readonly MobileLocales $locales,
        private readonly MobileAppSettingsService $appSettings,
        private readonly StoreSettingsService $storeSettings,
        private readonly StoreGeneralSettingsService $generalSettings,
        private readonly StoreAppearanceService $appearance,
        private readonly StoreThemeService $themes,
        private readonly StoreCountries $countries,
        private readonly StoreCurrencyService $currencies,
        private readonly StaticTextCatalog $catalog,
        private readonly StaticTextResolver $texts,
        private readonly FcmClient $fcm,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(?string $platform, ?string $appVersion, ?string $countryIso2): array
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $settings = $this->appSettings->get();
        $country = $this->country($countryIso2, $tenant);
        $locale = app()->getLocale();
        $features = $settings->featureFlags();
        $features[MobileFeature::PushNotifications->value] = $features[MobileFeature::PushNotifications->value] && $this->fcm->isConfigured();

        return [
            'store' => [
                'id' => (int) $tenant->getTenantKey(),
                'name' => (string) ($tenant->getAttribute('name') ?? config('app.name')),
                'url' => StoreSettingsService::storeUrl($tenant),
                'logo_url' => $this->storeSettings->logoUrl(),
                'support_email' => $this->generalSettings->supportEmail(),
            ],
            'branding' => $this->branding($settings, $country),
            'locale' => [
                'current' => $locale,
                'dir' => Locale::direction($locale),
                'default' => $this->locales->default(),
                'languages' => $this->locales->all(),
            ],
            'currency' => [
                'base' => Money::BASE_CURRENCY,
                'charge' => Money::BASE_CURRENCY,
                'default' => $this->defaultCurrency($country),
                'available' => $this->currencies->switcherCurrencies()->map(static fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimals' => $currency->decimals,
                    'rate' => (string) $currency->rate,
                    'is_base' => $currency->isBase(),
                ])->values()->all(),
            ],
            'countries' => [
                'default_id' => $country?->id,
                'available' => $this->countries->available()->map(static fn (Country $item): array => [
                    'id' => $item->id,
                    'iso2' => $item->iso2,
                    'name' => $item->name,
                    'flag' => $item->flag,
                    'phone_code' => $item->phone_code,
                    'currency_code' => $item->currency_code,
                ])->values()->all(),
            ],
            'features' => $features,
            'push' => ['enabled' => $features[MobileFeature::PushNotifications->value], 'provider' => 'fcm'],
            'app' => [
                'platform' => $platform,
                'version' => $appVersion,
                'update' => $this->update($settings, $platform, $appVersion),
                'maintenance' => [
                    'enabled' => $settings->maintenance,
                    'message' => $settings->maintenanceMessage[$locale] ?? $settings->maintenanceMessage['en'] ?? null,
                ],
            ],
            'texts' => ['version' => $this->textsVersion($locale), 'locale' => $locale],
            'api' => [
                'mobile' => url('/api/mobile/v1'),
                'store' => url('/store-api/v1'),
            ],
        ];
    }

    /**
     * Every storefront static text of the store in a locale (store edits → base value →
     * English → key), mobile keys included.
     *
     * @return array{locale: string, version: string, texts: array<string, string>}
     */
    public function texts(string $locale, ?string $group = null): array
    {
        $values = $this->resolvedTexts($locale);

        if ($group !== null && $group !== '') {
            $prefix = $group.'.';
            $values = array_filter($values, static fn (string $key): bool => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);
        }

        return ['locale' => $locale, 'version' => $this->textsVersion($locale), 'texts' => $values];
    }

    public function textsVersion(string $locale): string
    {
        return substr(sha1((string) json_encode($this->resolvedTexts($locale))), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    private function resolvedTexts(string $locale): array
    {
        if (isset($this->resolved[$locale])) {
            return $this->resolved[$locale];
        }

        $values = [];

        foreach ($this->catalog->keys() as $key) {
            $values[$key] = $this->texts->get($key, [], $locale);
        }

        ksort($values);

        return $this->resolved[$locale] = $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function branding(MobileAppSettingsData $settings, ?Country $country): array
    {
        $theme = $this->themes->resolve($country?->id);
        $colors = $this->appearance->colors();
        $splashLogo = null;

        if ($settings->splashLogoPath !== null && Storage::disk('public')->exists($settings->splashLogoPath)) {
            $splashLogo = Storage::disk('public')->url($settings->splashLogoPath);
        }

        return [
            'theme' => ['slug' => $theme->slug, 'family' => $theme->family->value, 'variant' => $theme->variant],
            'colors' => ['primary' => $colors->primary],
            'fonts' => [
                'ltr' => $colors->fontLtr?->family(),
                'rtl' => $colors->fontRtl?->family(),
            ],
            'splash' => [
                'background' => $settings->splashBackground ?? $colors->primary,
                'logo_url' => $splashLogo ?? $this->storeSettings->logoUrl(),
            ],
        ];
    }

    /**
     * @return array{state: string, min_version: string|null, latest_version: string|null, store_url: string|null}
     */
    private function update(MobileAppSettingsData $settings, ?string $platform, ?string $version): array
    {
        $release = $settings->release((string) $platform);
        $state = in_array($platform, ['ios', 'android'], true) ? $this->appSettings->updateState($platform, $version, $settings) : AppUpdateState::None;

        return [
            'state' => $state->value,
            'min_version' => $release->minVersion,
            'latest_version' => $release->latestVersion,
            'store_url' => $release->storeUrl,
        ];
    }

    private function country(?string $iso2, Tenant $tenant): ?Country
    {
        if ($iso2 !== null && $iso2 !== '' && ($country = $this->countries->findByIso2($iso2)) !== null) {
            return $country;
        }

        $default = $tenant->default_country_id;

        if ($default !== null && ($country = $this->countries->find((int) $default)) !== null) {
            return $country;
        }

        return $this->countries->available()->first();
    }

    private function defaultCurrency(?Country $country): string
    {
        $code = $country?->currency_code;

        if (! is_string($code) || $code === '') {
            return Money::BASE_CURRENCY;
        }

        $match = $this->currencies->switcherCurrencies()->first(fn (Currency $currency): bool => $currency->code === strtoupper($code) && $this->currencies->isCountryDefault($currency));

        return $match instanceof Currency ? $match->code : Money::BASE_CURRENCY;
    }
}
