<?php

namespace App\Modules\Localization;

use App\Modules\Billing\Events\LanguagePurchasePaid;
use App\Modules\Localization\Console\SyncStaticTextsCommand;
use App\Modules\Localization\Enums\GeocodingProvider;
use App\Modules\Localization\Jobs\SyncExchangeRates;
use App\Modules\Localization\Listeners\QueueStoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TranslationKeyLockRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use App\Modules\Localization\Repositories\Eloquent\CityRepository;
use App\Modules\Localization\Repositories\Eloquent\ContentTranslationLockRepository;
use App\Modules\Localization\Repositories\Eloquent\CountryRepository;
use App\Modules\Localization\Repositories\Eloquent\CurrencyRepository;
use App\Modules\Localization\Repositories\Eloquent\LanguageRepository;
use App\Modules\Localization\Repositories\Eloquent\StoreTranslationRunRepository;
use App\Modules\Localization\Repositories\Eloquent\TenantLanguageRepository;
use App\Modules\Localization\Repositories\Eloquent\TenantStaticTextRepository;
use App\Modules\Localization\Repositories\Eloquent\TranslationKeyLockRepository;
use App\Modules\Localization\Repositories\Eloquent\TranslationOverrideRepository;
use App\Modules\Localization\Services\CurrencyService;
use App\Modules\Localization\Services\Geocoding\IpLocator;
use App\Modules\Localization\Services\Geocoding\IpWhoIsLocator;
use App\Modules\Localization\Services\Geocoding\NominatimGeocoder;
use App\Modules\Localization\Services\Geocoding\ReverseGeocoder;
use App\Modules\Localization\Services\Geocoding\UnconfiguredGeocoder;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use App\Modules\Localization\Support\OverridingTranslationLoader;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
        TenantStaticTextRepositoryContract::class => TenantStaticTextRepository::class,
        TranslationOverrideRepositoryContract::class => TranslationOverrideRepository::class,
        TranslationKeyLockRepositoryContract::class => TranslationKeyLockRepository::class,
        StoreTranslationRunRepositoryContract::class => StoreTranslationRunRepository::class,
        ContentTranslationLockRepositoryContract::class => ContentTranslationLockRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->scoped(StoreCountries::class);

        require_once __DIR__.'/Support/helpers.php';

        /* Storefront static texts (decision #45) + translation editor state, per request. */
        $this->app->scoped(StaticTextCatalog::class);
        $this->app->scoped(StaticTextResolver::class);
        $this->app->scoped(TranslationKeyLockService::class);
        $this->app->scoped(TranslationFileRegistry::class);

        /* Admin translation overrides (ADM-092) merged over lang/* files. */
        $this->app->extend('translation.loader', static fn ($loader, $app): OverridingTranslationLoader => $loader instanceof OverridingTranslationLoader
            ? $loader
            : new OverridingTranslationLoader($loader, static fn (): TranslationOverrideRepositoryContract => $app->make(TranslationOverrideRepositoryContract::class)));

        /* Address lookup providers: Admin › Integrations › Geocoding; off = not configured. */
        $this->app->bind(ReverseGeocoder::class, static fn (): ReverseGeocoder => match (config('services.geocoding.enabled') === true ? GeocodingProvider::tryFrom((string) config('services.geocoding.reverse_provider')) : false) {
            false => new UnconfiguredGeocoder,
            GeocodingProvider::Nominatim => new NominatimGeocoder,
            default => throw new InvalidArgumentException('Unsupported reverse geocoding provider.'),
        });

        $this->app->bind(IpLocator::class, static fn (): IpLocator => match (config('services.geocoding.enabled') === true ? GeocodingProvider::tryFrom((string) config('services.geocoding.ip_provider')) : false) {
            false => new UnconfiguredGeocoder,
            GeocodingProvider::IpWhoIs => new IpWhoIsLocator,
            default => throw new InvalidArgumentException('Unsupported IP location provider.'),
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(LanguagePurchasePaid::class, QueueStoreTranslationRun::class);

        Blade::directive('st', static fn (string $expression): string => "<?php echo e(__st({$expression})); ?>");

        if ($this->app->runningInConsole()) {
            $this->commands([SyncStaticTextsCommand::class]);
        }

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
