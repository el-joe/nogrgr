<?php

namespace App\Modules\Platform\Services;

use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Platform\DTOs\SetupProgressData;
use App\Modules\Platform\DTOs\SetupStepData;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\SetupStep;
use App\Modules\Platform\Enums\SetupStepState;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Routing\VendorPanel;
use App\Shared\Routing\VendorPanelMode;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Route;

/**
 * Vendor onboarding (VEN-007): the panel tour and the 8-step setup checklist of the
 * current store. Every step is computed from real store data; a step whose page ships
 * later carries `availableAfter` instead of a link.
 */
class OnboardingService extends BaseService
{
    /**
     * Panel tour stops: key => vendor route (without prefix) the stop points at.
     *
     * @var array<string, string>
     */
    public const TOUR = [
        'dashboard' => 'dashboard',
        'products' => 'catalog.products.index',
        'orders' => 'orders.index',
        'store' => 'store.appearance',
        'finance' => 'finance.subscription',
        'settings' => 'settings.languages.index',
    ];

    public function __construct(
        private readonly StoreSettingsService $settings,
        private readonly TenantLanguageRepositoryContract $languages,
        private readonly StoreProductRepositoryContract $storeProducts,
        private readonly SetupWizardService $wizard,
    ) {}

    public function progress(Tenant $tenant): SetupProgressData
    {
        return $this->build($tenant);
    }

    public function tourFinished(): bool
    {
        return $this->settings->timestamp(StoreSettingKey::TourCompletedAt) !== null
            || $this->settings->timestamp(StoreSettingKey::TourDismissedAt) !== null;
    }

    public function completeTour(): void
    {
        $this->settings->stamp(StoreSettingKey::TourCompletedAt);
    }

    public function dismissTour(): void
    {
        $this->settings->stamp(StoreSettingKey::TourDismissedAt);
    }

    public function skipPaymentReadiness(): void
    {
        $this->settings->stamp(StoreSettingKey::PaymentReadinessSkippedAt);
    }

    /**
     * Tour stops with their link when the page exists.
     *
     * @return list<array{key: string, url: ?string}>
     */
    public function tourStops(): array
    {
        $stops = [];

        foreach (self::TOUR as $key => $route) {
            $stops[] = ['key' => $key, 'url' => self::accessibleVendorUrl($route)];
        }

        return $stops;
    }

    private function build(Tenant $tenant): SetupProgressData
    {
        $done = [
            SetupStep::StoreDetails->value => filled($tenant->name) && filled($tenant->owner_email) && filled($tenant->owner_phone),
            SetupStep::CountriesLanguages->value => ($tenant->selling_country_ids ?? []) !== [],
            SetupStep::Theme->value => $this->wizard->themeFamily($tenant) !== null,
            SetupStep::ImportProducts->value => $this->storeProducts->hasAny(),
            SetupStep::Logo->value => $this->settings->logoPath() !== null,
            SetupStep::PaymentReadiness->value => false,
            SetupStep::Domain->value => $tenant->domains()->where('type', DomainType::Custom->value)->exists(),
            SetupStep::Tour->value => $this->tourFinished(),
        ];

        $paymentSkipped = $this->settings->timestamp(StoreSettingKey::PaymentReadinessSkippedAt) !== null;
        $steps = [];
        $completed = 0;
        $nextAssigned = false;

        foreach (SetupStep::cases() as $step) {
            $state = match (true) {
                $done[$step->value] => SetupStepState::Done,
                $step === SetupStep::PaymentReadiness && $paymentSkipped => SetupStepState::Skipped,
                $step->isOptional() => SetupStepState::Optional,
                ! $nextAssigned => SetupStepState::Next,
                default => SetupStepState::Pending,
            };

            $nextAssigned = $nextAssigned || $state === SetupStepState::Next;
            $completed += $state->isComplete() ? 1 : 0;
            $route = $step->route();
            $pageUrl = $route !== null ? self::vendorUrl($route) : null;
            $url = $pageUrl !== null && self::canOpen($route) ? $pageUrl : null;

            $steps[] = new SetupStepData(
                step: $step,
                state: $state,
                title: (string) __('platform::onboarding.steps.'.$step->value.'.title'),
                detail: $this->detail($step, $tenant, $state),
                url: $url,
                availableAfter: $route !== null && $pageUrl === null ? (string) __('platform::onboarding.steps.'.$step->value.'.available_after') : null,
            );
        }

        return new SetupProgressData($steps, $completed, count($steps), $done[SetupStep::Tour->value]);
    }

    private function detail(SetupStep $step, Tenant $tenant, SetupStepState $state): string
    {
        $key = 'platform::onboarding.steps.'.$step->value.'.detail';

        return match ($step) {
            SetupStep::CountriesLanguages => $state === SetupStepState::Done
                ? (string) trans_choice('platform::onboarding.steps.countries_languages.summary', count($tenant->selling_country_ids ?? []), [
                    'count' => count($tenant->selling_country_ids ?? []),
                    'languages' => implode(', ', $this->languages->codes() ?: ['ar', 'en']),
                ])
                : (string) __($key),
            SetupStep::Theme => $state === SetupStepState::Done
                ? (string) $this->wizard->themeLabel($tenant)
                : (string) __($key),
            default => (string) __($key),
        };
    }

    /**
     * URL of a vendor panel page in the current host mode, or null while the route is
     * not registered (the page ships in a later phase).
     */
    public static function vendorUrl(string $route): ?string
    {
        $prefix = VendorPanel::mode() === VendorPanelMode::CentralHost ? VendorPanel::CENTRAL_NAME_PREFIX : VendorPanel::TENANT_NAME_PREFIX;

        return Route::has($prefix.$route) ? vendor_route($route) : null;
    }

    /**
     * Like vendorUrl(), but null when the signed-in member lacks the route's
     * `permission:` middleware (RQA-VEN-007: never show a link that answers 403).
     */
    public static function accessibleVendorUrl(string $route): ?string
    {
        $url = self::vendorUrl($route);

        return $url !== null && self::canOpen($route) ? $url : null;
    }

    /**
     * The vendor member passes every `permission:a|b,vendor` middleware of the route.
     */
    public static function canOpen(string $route): bool
    {
        $prefix = VendorPanel::mode() === VendorPanelMode::CentralHost ? VendorPanel::CENTRAL_NAME_PREFIX : VendorPanel::TENANT_NAME_PREFIX;
        $definition = Route::getRoutes()->getByName($prefix.$route);

        if ($definition === null) {
            return false;
        }

        $user = auth('vendor')->user();

        foreach ($definition->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            $permissions = explode('|', explode(',', substr($middleware, strlen('permission:')))[0]);

            if ($user === null || ! $user->canAny($permissions)) {
                return false;
            }
        }

        return true;
    }
}
