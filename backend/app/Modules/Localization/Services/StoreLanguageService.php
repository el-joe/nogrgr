<?php

namespace App\Modules\Localization\Services;

use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\AddonPurchaseService;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Localization\DTOs\StoreLanguageSettingsData;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * Vendor › Settings › Languages (VEN-073 / 073a, decision #45): the store's languages
 * (ar + en free and always on; bought ones switch on when their paid AI translation run
 * completes), default + switcher order, buying a language through the shared invoice
 * checkout (hosted page or the inline Stripe card) and the per-language run status:
 * pending payment → queued → translating % → done | failed (+ retry).
 */
class StoreLanguageService extends BaseService
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly LanguageRepositoryContract $languages,
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
        private readonly StoreTranslationRunRepositoryContract $runs,
        private readonly AddonPurchaseService $purchases,
        private readonly InvoiceCheckoutService $checkout,
    ) {}

    /**
     * The store's languages with their central row, plus bought languages whose run has
     * not finished yet (so their status shows in the list).
     *
     * @return Collection<int, array{code: string, language: Language|null, row: TenantLanguage|null, run: StoreTranslationRun|null, core: bool}>
     */
    public function storeLanguages(Tenant $tenant): Collection
    {
        $rows = $tenant->run(fn () => $this->tenantLanguages->ordered());
        $central = $this->languages->query()->get()->keyBy('code');
        $runs = $this->runs->latestByLanguage((int) $tenant->id);
        $list = [];

        foreach ($rows as $row) {
            $list[] = $this->entry($row->language_code, $central->get($row->language_code), $row, $runs->get($row->language_code));
        }

        foreach ($runs as $code => $run) {
            if (! $rows->contains('language_code', $code)) {
                $list[] = $this->entry((string) $code, $central->get($code), null, $run);
            }
        }

        return collect($list);
    }

    /**
     * @return array{code: string, language: Language|null, row: TenantLanguage|null, run: StoreTranslationRun|null, core: bool}
     */
    private function entry(string $code, ?Language $language, ?TenantLanguage $row, ?StoreTranslationRun $run): array
    {
        return ['code' => $code, 'language' => $language, 'row' => $row, 'run' => $run, 'core' => in_array($code, Language::CORE, true)];
    }

    /**
     * Languages the store can still buy, with its open (unpaid) purchase.
     *
     * @return Collection<int, array{language: Language, purchase: AddonPurchase|null}>
     */
    public function purchasable(Tenant $tenant): Collection
    {
        $runs = $this->runs->latestByLanguage((int) $tenant->id);

        return $this->languageService->availableToPurchase($tenant)
            ->reject(fn (Language $language): bool => $runs->has($language->code))
            ->map(fn (Language $language): array => [
                'language' => $language,
                'purchase' => $this->purchases->openLanguagePurchase((int) $tenant->id, $language->code),
            ])
            ->values();
    }

    public function findPurchasable(Tenant $tenant, int $languageId): Language
    {
        $row = $this->purchasable($tenant)->first(static fn (array $row): bool => $row['language']->id === $languageId);

        return $row['language'] ?? throw TranslationRuleException::languageNotPurchasable();
    }

    /**
     * Default, switched-on languages and their order. ar / en stay on; a language only
     * becomes default while it is on.
     */
    public function save(Tenant $tenant, StoreLanguageSettingsData $data): void
    {
        $tenant->run(function () use ($data): void {
            $rows = $this->tenantLanguages->ordered()->keyBy('language_code');
            $enabled = array_values(array_unique([...Language::CORE, ...array_intersect($data->enabled, $rows->keys()->all())]));

            if (! in_array($data->default, $enabled, true)) {
                throw TranslationRuleException::defaultMustBeEnabled();
            }

            $order = array_values(array_unique([...array_intersect($data->order, $rows->keys()->all()), ...$rows->keys()->all()]));

            $this->transaction(function () use ($rows, $enabled, $order, $data): void {
                foreach ($order as $position => $code) {
                    /** @var TenantLanguage $row */
                    $row = $rows->get($code);
                    $this->tenantLanguages->update($row, [
                        'is_enabled' => in_array($code, $enabled, true),
                        'is_default' => $code === $data->default,
                        'sort_order' => $position + 1,
                    ]);
                }
            }, (new TenantLanguage)->getConnectionName());
        });
    }

    /**
     * Buy a language: purchase + unpaid invoice (idempotent), then the checkout.
     *
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws PaymentGatewayException
     */
    public function startPurchase(Tenant $tenant, Language $language, PaymentGatewayCode $gateway, ?string $email): array
    {
        $purchase = $this->purchases->purchaseLanguage((int) $tenant->id, $language->id, $language->code, (string) $language->name, $language->price(), $tenant->name);
        $invoice = $this->purchases->invoiceFor($purchase) ?? throw TranslationRuleException::languageNotPurchasable();

        return $this->checkout->start(
            $invoice,
            $gateway,
            (string) __('localization::vendor_languages.checkout_description', ['language' => $language->name, 'app' => config('app.name')]),
            static fn (PaymentLog $log): array => [
                'success' => vendor_route('settings.languages.payment.result', ['payment' => $log->id]),
                'cancel' => vendor_route('settings.languages.payment.cancel', ['payment' => $log->id]),
            ],
            $email,
            app()->getLocale(),
            ['language_id' => $language->id, 'language_code' => $language->code],
        );
    }

    public function paymentLog(Tenant $tenant, int $logId): ?PaymentLog
    {
        $log = $this->checkout->logFor((int) $tenant->id, $logId);

        return $log !== null && isset(($log->payload ?? [])['language_id']) ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirmPayment(PaymentLog $log): PaymentLog
    {
        return $this->checkout->confirm($log);
    }

    public function cancelPayment(PaymentLog $log): PaymentLog
    {
        return $this->checkout->cancel($log);
    }

    public function languageName(int $languageId): string
    {
        $language = $this->languages->find($languageId);

        return $language instanceof Language ? (string) $language->name : '';
    }

    /**
     * Paid purchase → its translation run (once per purchase), announced to the P10 run.
     */
    public function queueRun(int $tenantId, ?int $languageId, string $code, ?int $purchaseId): StoreTranslationRun
    {
        $existing = $purchaseId !== null ? $this->runs->forPurchase($purchaseId) : null;

        if ($existing !== null) {
            return $existing;
        }

        /** @var StoreTranslationRun $run */
        $run = $this->runs->create([
            'tenant_id' => $tenantId,
            'language_id' => $languageId,
            'language_code' => strtolower($code),
            'addon_purchase_id' => $purchaseId,
            'status' => TranslationRunStatus::Queued,
            'queued_at' => now(),
        ]);

        $this->dispatchAfterCommit(new StoreTranslationRunQueued($run->id, $tenantId, $run->language_code));

        return $run;
    }

    /**
     * Put a failed run back in the queue (the P10 run resumes, "retry failed only").
     */
    public function retry(Tenant $tenant, string $code): StoreTranslationRun
    {
        $run = $this->runs->latestByLanguage((int) $tenant->id)->get(strtolower($code));

        if ($run === null || $run->status !== TranslationRunStatus::Failed) {
            throw TranslationRuleException::runNotRetryable();
        }

        /** @var StoreTranslationRun $run */
        $run = $this->runs->update($run, [
            'status' => TranslationRunStatus::Queued,
            'attempts' => $run->attempts + 1,
            'last_error' => null,
            'queued_at' => now(),
            'finished_at' => null,
        ]);

        $this->dispatchAfterCommit(new StoreTranslationRunQueued($run->id, (int) $tenant->id, $run->language_code));

        return $run;
    }
}
