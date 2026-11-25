<?php

namespace App\Modules\Localization;

use App\Modules\Localization\Enums\GeocodingProvider;
use App\Modules\Localization\Jobs\SyncExchangeRates;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Repositories\Eloquent\CityRepository;
use App\Modules\Localization\Repositories\Eloquent\CountryRepository;
use App\Modules\Localization\Repositories\Eloquent\CurrencyRepository;
use App\Modules\Localization\Repositories\Eloquent\LanguageRepository;
use App\Modules\Localization\Repositories\Eloquent\TenantLanguageRepository;
use App\Modules\Localization\Services\CurrencyService;
use App\Modules\Localization\Services\Geocoding\IpLocator;
use App\Modules\Localization\Services\Geocoding\IpWhoIsLocator;
use App\Modules\Localization\Services\Geocoding\NominatimGeocoder;
use App\Modules\Localization\Services\Geocoding\ReverseGeocoder;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Requests per minute and visitor IP for the geocoding endpoints.
     */
    public const GEOCODING_PER_MINUTE = 20;

    /**
     * Provider calls per minute for the whole platform (Nominatim allows ~1/s).
     */
    public const GEOCODING_GLOBAL_PER_MINUTE = 60;

    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        CountryRepositoryContract::class => CountryRepository::class,
        CityRepositoryContract::class => CityRepository::class,
        LanguageRepositoryContract::class => LanguageRepository::class,
        CurrencyRepositoryContract::class => CurrencyRepository::class,
        TenantLanguageRepositoryContract::class => TenantLanguageRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->scoped(StoreCountries::class);

        $this->app->bind(ReverseGeocoder::class, static fn (): ReverseGeocoder => match (GeocodingProvider::tryFrom((string) config('services.geocoding.reverse_provider'))) {
            GeocodingProvider::Nominatim => new NominatimGeocoder,
            default => throw new InvalidArgumentException('Unsupported reverse geocoding provider.'),
        });

        $this->app->bind(IpLocator::class, static fn (): IpLocator => match (GeocodingProvider::tryFrom((string) config('services.geocoding.ip_provider'))) {
            GeocodingProvider::IpWhoIs => new IpWhoIsLocator,
            default => throw new InvalidArgumentException('Unsupported IP location provider.'),
        });
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('geocoding', static fn (Request $request): array => [
            Limit::perMinute(self::GEOCODING_PER_MINUTE)->by('geocoding:'.$request->ip()),
            Limit::perMinute(self::GEOCODING_GLOBAL_PER_MINUTE)->by('geocoding:global'),
        ]);

        /* Daily exchange rates (USD base) from open.er-api.com. */
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->job(new SyncExchangeRates)->dailyAt(CurrencyService::SYNC_AT)->name('localization:sync-exchange-rates')->onOneServer();
        });
    }
}
