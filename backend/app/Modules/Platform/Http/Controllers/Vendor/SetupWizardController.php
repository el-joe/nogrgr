<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Billing\Support\PaymentStartResponse;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Enums\SetupWizardStep;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Vendor\LanguagePurchaseRequest;
use App\Modules\Platform\Http\Requests\Vendor\SetupCountriesRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\OnboardingService;
use App\Modules\Platform\Services\SetupWizardService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor setup wizard (VEN-007, Figma 82:99932): ?step=countries|languages|theme|launch,
 * save countries (direct the first time, a change request afterwards), buy a language
 * through the hosted checkout and its return pages, launch the store.
 */
class SetupWizardController extends Controller
{
    public function __construct(private readonly SetupWizardService $wizard) {}

    public function show(Request $request, CountryService $countries, InvoiceCheckoutService $checkout): View
    {
        $tenant = $this->tenant();
        $step = SetupWizardStep::tryFrom((string) $request->query('step')) ?? $this->firstOpenStep($tenant);
        $staff = $this->staff($request);

        return view('platform::vendor.onboarding.wizard', [
            'tenant' => $tenant,
            'step' => $step,
            'progress' => $this->wizard->progress($tenant),
            'countries' => $countries->active(),
            'selected' => $this->wizard->sellingCountryIds($tenant),
            'pendingChange' => $this->wizard->pendingCountriesChange($tenant),
            'storeLanguages' => $this->wizard->storeLanguageCodes($tenant),
            'purchasable' => $step === SetupWizardStep::Languages ? $this->wizard->purchasableLanguages($tenant) : collect(),
            'gateways' => $step === SetupWizardStep::Languages ? $checkout->gateways() : collect(),
            'canBuyLanguages' => $staff->can('settings.languages.manage'),
            'theme' => $this->wizard->themeLabel($tenant),
            'themesUrl' => OnboardingService::vendorUrl('store.themes.index'),
            'launchedAt' => $this->wizard->launchedAt(),
        ]);
    }

    public function saveCountries(SetupCountriesRequest $request): RedirectResponse
    {
        try {
            $result = $this->wizard->saveCountries($this->tenant(), $request->toData());
        } catch (RequestRuleException $exception) {
            return redirect()->to($this->stepUrl(SetupWizardStep::Countries))->with('error', $exception->getMessage());
        }

        return redirect()->to($this->stepUrl(SetupWizardStep::Languages))
            ->with($result === 'requested' ? 'info' : 'success', __('platform::setup.countries.'.$result));
    }

    /**
     * Form post → hosted checkout; the inline Stripe card posts as JSON (decision #78 b).
     */
    public function purchaseLanguage(LanguagePurchaseRequest $request, int $language, PaymentDriverRegistry $drivers): RedirectResponse|JsonResponse
    {
        $tenant = $this->tenant();
        $inline = $request->expectsJson();
        $start = fn (): array => $this->wizard->purchaseLanguage($tenant, $this->wizard->findPurchasableLanguage($tenant, $language), $request->gateway(), $this->staff($request)->email);

        try {
            $started = $inline ? $drivers->inline($start) : $start();
        } catch (PaymentGatewayException|PlatformRuleException $exception) {
            return $inline ? JsonResponder::error($exception->getMessage(), 422) : redirect()->to($this->stepUrl(SetupWizardStep::Languages))->with('error', $exception->getMessage());
        }

        return $inline ? PaymentStartResponse::json($started) : redirect()->away($started['redirect']);
    }

    public function languagePaymentResult(int $payment): RedirectResponse
    {
        $log = $this->log($payment);

        if ($log->status === PaymentLogStatus::Pending) {
            try {
                $log = $this->wizard->confirmLanguagePayment($log);
            } catch (PaymentGatewayException) {
                // The webhook settles it later.
            }
        }

        return $this->languageResult($log, false);
    }

    public function languagePaymentCancel(int $payment): RedirectResponse
    {
        return $this->languageResult($this->wizard->cancelLanguagePayment($this->log($payment)), true);
    }

    public function launch(): RedirectResponse
    {
        try {
            $this->wizard->launch($this->tenant());
        } catch (PlatformRuleException $exception) {
            return redirect()->to($this->stepUrl(SetupWizardStep::Countries))->with('error', $exception->getMessage());
        }

        return redirect()->to(vendor_route('dashboard'))->with('success', __('platform::setup.launch.done'));
    }

    private function languageResult(PaymentLog $log, bool $cancelled): RedirectResponse
    {
        $name = $this->wizard->languageName((int) (($log->payload ?? [])['language_id'] ?? 0));
        [$type, $key] = match ($log->status) {
            PaymentLogStatus::Captured => ['success', 'paid'],
            PaymentLogStatus::Pending => ['info', 'processing'],
            default => ['error', $cancelled ? 'cancelled' : 'failed'],
        };

        return redirect()->to($this->stepUrl(SetupWizardStep::Languages))->with($type, __('platform::setup.languages.'.$key, ['language' => $name]));
    }

    private function log(int $payment): PaymentLog
    {
        $log = $this->wizard->languagePaymentLog($this->tenant(), $payment);
        abort_if($log === null, 404);

        return $log;
    }

    private function firstOpenStep(Tenant $tenant): SetupWizardStep
    {
        foreach ($this->wizard->progress($tenant) as $step => $done) {
            if (! $done) {
                return SetupWizardStep::from($step);
            }
        }

        return SetupWizardStep::Launch;
    }

    private function stepUrl(SetupWizardStep $step): string
    {
        return vendor_route('onboarding.setup', ['step' => $step->value]);
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
