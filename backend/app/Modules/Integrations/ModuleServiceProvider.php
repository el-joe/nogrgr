<?php

namespace App\Modules\Integrations;

use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Integrations\Listeners\RecordGatewayVolume;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Modules\Integrations\Repositories\Contracts\GatewayCheckoutRouteRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\GatewayLimitUsageRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Repositories\Eloquent\GatewayCheckoutRouteRepository;
use App\Modules\Integrations\Repositories\Eloquent\GatewayLimitUsageRepository;
use App\Modules\Integrations\Repositories\Eloquent\PaymentGatewayRepository;
use App\Modules\Integrations\Repositories\Eloquent\SettingsRepository;
use App\Modules\Integrations\Services\IntegrationConfigOverlay;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        PaymentGatewayRepositoryContract::class => PaymentGatewayRepository::class,
        GatewayCheckoutRouteRepositoryContract::class => GatewayCheckoutRouteRepository::class,
        GatewayLimitUsageRepositoryContract::class => GatewayLimitUsageRepository::class,
    ];

    /**
     * The settings store memoises values for the request / job.
     *
     * @var array<class-string, class-string>
     */
    public $singletons = [
        SettingsRepositoryContract::class => SettingsRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        // Per request / job: the inline Stripe card mode (PaymentDriverRegistry::inline()).
        $this->app->scoped(PaymentDriverRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Saved Integrations settings override env config at runtime (and before every
        // queued job, so long-running workers pick up changes made in the panel).
        $this->app->booted(static fn ($app) => $app->make(IntegrationConfigOverlay::class)->apply());
        Event::listen(JobProcessing::class, static fn () => app(IntegrationConfigOverlay::class)->apply());

        // Gateway limits (ADM-100c): platform-account volume per store and month.
        Event::listen(OrderPaid::class, RecordGatewayVolume::class);
    }
}
