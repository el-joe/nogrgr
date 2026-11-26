<?php

namespace App\Modules\Affiliates;

use App\Modules\Affiliates\Http\Middleware\TrackAffiliateReferral;
use App\Modules\Affiliates\Http\ViewComposers\VendorPartnerWidgetComposer;
use App\Modules\Affiliates\Listeners\ApproveConversionOnPayment;
use App\Modules\Affiliates\Listeners\CreateVendorAffiliate;
use App\Modules\Affiliates\Listeners\RecordReferralOnRegistration;
use App\Modules\Affiliates\Listeners\ReverseConversionOnRefund;
use App\Modules\Affiliates\Listeners\SendAffiliateNotifications;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\ClickRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PortalStatsRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\SsoTokenRepositoryContract;
use App\Modules\Affiliates\Repositories\Eloquent\AffiliateRepository;
use App\Modules\Affiliates\Repositories\Eloquent\ClickRepository;
use App\Modules\Affiliates\Repositories\Eloquent\ConversionRepository;
use App\Modules\Affiliates\Repositories\Eloquent\PayoutRepository;
use App\Modules\Affiliates\Repositories\Eloquent\PayoutRequestRepository;
use App\Modules\Affiliates\Repositories\Eloquent\PortalStatsRepository;
use App\Modules\Affiliates\Repositories\Eloquent\PromoCodeRepository;
use App\Modules\Affiliates\Repositories\Eloquent\SsoTokenRepository;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Events\RegistrationCompleted;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

/**
 * Affiliate program (central): referral tracking on the central website, conversions
 * (first paid subscription), payouts, promo codes, admin pages ADM-060…063.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AffiliateRepositoryContract::class => AffiliateRepository::class,
        ClickRepositoryContract::class => ClickRepository::class,
        ConversionRepositoryContract::class => ConversionRepository::class,
        PayoutRepositoryContract::class => PayoutRepository::class,
        PayoutRequestRepositoryContract::class => PayoutRequestRepository::class,
        PromoCodeRepositoryContract::class => PromoCodeRepository::class,
        PortalStatsRepositoryContract::class => PortalStatsRepository::class,
        SsoTokenRepositoryContract::class => SsoTokenRepository::class,
    ];

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('affiliate.referral', TrackAffiliateReferral::class);

        parent::boot();

        Event::listen(RegistrationCompleted::class, RecordReferralOnRegistration::class);
        Event::listen(PaymentCaptured::class, ApproveConversionOnPayment::class);
        Event::listen(AffiliateConversionReversalRequested::class, ReverseConversionOnRefund::class);
        Event::listen(TenantProvisioned::class, CreateVendorAffiliate::class);
        Event::subscribe(SendAffiliateNotifications::class);

        View::composer('affiliates::vendor._partner', VendorPartnerWidgetComposer::class);

        Broadcast::channel('affiliate.{id}', static fn (mixed $user, string $id): bool => $user instanceof Affiliate
            && (string) $user->getKey() === $id, ['guards' => ['affiliate']]);
    }
}
