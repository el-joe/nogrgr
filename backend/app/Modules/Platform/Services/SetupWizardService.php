<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\AddonPurchaseService;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Catalog\DTOs\Requests\TenantChangeSubmissionData;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Repositories\Contracts\TenantChangeRequestRepositoryContract;
use App\Modules\Catalog\Services\Requests\TenantChangeService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Services\LanguageService;
use App\Modules\Platform\DTOs\SetupCountriesData;
use App\Modules\Platform\Enums\SetupWizardStep;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * Vendor setup wizard (VEN-007, Figma 82:99932): countries → languages → theme → launch.
 *
 *  - Countries: the first choice is saved on the store directly; once the store sells
 *    somewhere, a change becomes a store change request (TenantChangeService, admin
 *    approval — "You can add more later (admin approval)"). The default country must be
 *    one of the store's countries.
 *  - Languages: ar + en are always on; any other active priced language is bought
 *    (AddonPurchaseService::purchaseLanguage, decision #45) and its invoice paid through
 *    the hosted checkout (InvoiceCheckoutService).
 *  - Theme: chosen on the themes page (P7); until it exists the step says so.
 *  - Launch: stamps `launched_at` in the store settings.
 */
class SetupWizardService extends BaseService
{
    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly TenantChangeService $changes,
        private readonly TenantChangeRequestRepositoryContract $changeRequests,
        private readonly LanguageService $languageService,
        private readonly LanguageRepositoryContract $languages,
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
        private readonly AddonPurchaseService $purchases,
        private readonly InvoiceCheckoutService $checkout,
        private readonly StoreSettingsService $settings,
        private readonly StoreThemeService $themes,
    ) {}

    /**
     * Completion of each wizard step.
     *
     * @return array<string, bool>
     */
    public function progress(Tenant $tenant): array
    {
        return [
            SetupWizardStep::Countries->value => $this->sellingCountryIds($tenant) !== [],
            SetupWizardStep::Languages->value => $this->sellingCountryIds($tenant) !== [],
            SetupWizardStep::Theme->value => $this->themeFamily($tenant) !== null,
            SetupWizardStep::Launch->value => $this->launchedAt() !== null,
        ];
    }

    /**
     * @return list<int>
     */
    public function sellingCountryIds(Tenant $tenant): array
    {
        return array_values(array_map('intval', array_filter((array) ($tenant->selling_country_ids ?? []), 'is_numeric')));
    }

    /**
     * A countries change waiting for the admin, if any.
     */
    public function pendingCountriesChange(Tenant $tenant): ?TenantChangeRequest
    {
        return $this->changeRequests->pendingFor($tenant->id, TenantChangeType::Countries->value);
    }

    /**
     * @return 'saved'|'requested'
     *
     * @throws RequestRuleException
     */
    public function saveCountries(Tenant $tenant, SetupCountriesData $data): string
    {
        $current = $this->sellingCountryIds($tenant);

        if ($current === []) {
            $this->tenants->update($tenant, ['selling_country_ids' => $data->countryIds, 'default_country_id' => $data->defaultCountryId]);

            return 'saved';
        }

        $requested = $data->countryIds;
        sort($requested);
        $sorted = $current;
        sort($sorted);

        if ($requested !== $sorted) {
            $this->changes->submit(new TenantChangeSubmissionData(
                tenantId: $tenant->id,
                type: TenantChangeType::Countries,
                requestedIds: $data->countryIds,
                vendorNote: null,
                submittedBy: $data->submittedBy,
            ));
        }

        if (in_array($data->defaultCountryId, $current, true) && $tenant->default_country_id !== $data->defaultCountryId) {
            $this->tenants->update($tenant, ['default_country_id' => $data->defaultCountryId]);
        }

        return $requested !== $sorted ? 'requested' : 'saved';
    }

    /**
     * Language codes switched on for the store (ar / en at least).
     *
     * @return list<string>
     */
    public function storeLanguageCodes(Tenant $tenant): array
    {
        /** @var list<string> $codes */
        $codes = $tenant->run(fn (): array => $this->tenantLanguages->codes());

        return array_values(array_unique([...Language::CORE, ...$codes]));
    }

    /**
     * Languages offered for purchase with the store's open (unpaid) purchase, if any.
     *
     * @return Collection<int, array{language: Language, purchase: AddonPurchase|null}>
     */
    public function purchasableLanguages(Tenant $tenant): Collection
    {
        return $this->languageService->availableToPurchase($tenant)->map(fn (Language $language): array => [
            'language' => $language,
            'purchase' => $this->purchases->openLanguagePurchase($tenant->id, $language->code),
        ]);
    }

    public function findPurchasableLanguage(Tenant $tenant, int $languageId): Language
    {
        $language = $this->languageService->availableToPurchase($tenant)->first(static fn (Language $language): bool => $language->id === $languageId);

        return $language ?? throw PlatformRuleException::languageNotPurchasable();
    }

    /**
     * Buy a language: purchase + unpaid invoice (idempotent), then the hosted checkout.
     *
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws PaymentGatewayException
     */
    public function purchaseLanguage(Tenant $tenant, Language $language, PaymentGatewayCode $gateway, ?string $email): array
    {
        $purchase = $this->purchases->purchaseLanguage($tenant->id, $language->id, $language->code, (string) $language->name, $language->price(), $tenant->name);
        $invoice = $this->purchases->invoiceFor($purchase) ?? throw PlatformRuleException::languageNotPurchasable();

        return $this->checkout->start(
            $invoice,
            $gateway,
            (string) __('platform::setup.languages.checkout_description', ['language' => $language->name, 'app' => config('app.name')]),
            static fn (PaymentLog $log): array => [
                'success' => vendor_route('onboarding.setup.languages.payment.result', ['payment' => $log->id]),
                'cancel' => vendor_route('onboarding.setup.languages.payment.cancel', ['payment' => $log->id]),
            ],
            $email,
            app()->getLocale(),
            ['language_id' => $language->id, 'language_code' => $language->code],
        );
    }

    public function languagePaymentLog(Tenant $tenant, int $logId): ?PaymentLog
    {
        $log = $this->checkout->logFor($tenant->id, $logId);

        return $log !== null && isset(($log->payload ?? [])['language_id']) ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirmLanguagePayment(PaymentLog $log): PaymentLog
    {
        return $this->checkout->confirm($log);
    }

    public function cancelLanguagePayment(PaymentLog $log): PaymentLog
    {
        return $this->checkout->cancel($log);
    }

    public function languageName(int $languageId): string
    {
        $language = $this->languages->find($languageId);

        return $language instanceof Language ? (string) $language->name : '';
    }

    public function themeFamily(Tenant $tenant): ?string
    {
        /* The theme saved on Store › Themes (VEN-030) wins over the sign-up pick. */
        if (tenancy()->initialized && (string) tenant()?->getTenantKey() === (string) $tenant->getKey() && $this->themes->config()['default'] !== null) {
            return $this->themes->resolve(null)->family->value;
        }

        $family = $tenant->getAttribute('theme_family');

        return is_string($family) && $family !== '' ? $family : null;
    }

    public function themeLabel(Tenant $tenant): ?string
    {
        $family = $this->themeFamily($tenant);

        return $family !== null ? ucfirst($family) : null;
    }

    public function launchedAt(): ?string
    {
        return $this->settings->timestamp(StoreSettingKey::LaunchedAt);
    }

    /**
     * @throws PlatformRuleException without selling countries
     */
    public function launch(Tenant $tenant): void
    {
        if ($this->sellingCountryIds($tenant) === []) {
            throw PlatformRuleException::launchNeedsCountries();
        }

        if ($this->launchedAt() === null) {
            $this->settings->stamp(StoreSettingKey::LaunchedAt);
        }
    }
}
