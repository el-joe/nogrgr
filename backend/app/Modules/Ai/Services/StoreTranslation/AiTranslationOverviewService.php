<?php

namespace App\Modules\Ai\Services\StoreTranslation;

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Models\StoreTranslationItem;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Modules\Ai\Support\StoreTranslationRegistry;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Enums\TranslationRunTrigger;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Localization\Services\StoreLanguageService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * Vendor › Settings › AI translation (VEN-074 / 074a, decisions #45 / #98 / #99): one
 * USD price per language translates the whole store, no word quota. Builds the run
 * stats (KPI cards), the live per-section progress of the current run (10 sections of
 * the StoreTranslationRegistry), the per-language card, and wraps the shared language
 * purchase / run queue / retry of StoreLanguageService for this page.
 */
class AiTranslationOverviewService extends BaseService
{
    /**
     * Progress rows of the page (Figma 180:28706) → run sections they add up.
     *
     * @var array<string, list<StoreTranslationSection>>
     */
    public const SECTION_GROUPS = [
        'categories' => [StoreTranslationSection::Categories],
        'own_products' => [StoreTranslationSection::Products, StoreTranslationSection::Variants],
        'central_products' => [StoreTranslationSection::ProductOverrides, StoreTranslationSection::VariantOverrides],
        'flash_sales' => [StoreTranslationSection::FlashSales],
        'banners' => [StoreTranslationSection::Banners],
        'menu_links' => [StoreTranslationSection::MenuLinks],
        'pages' => [StoreTranslationSection::Pages],
        'email_templates' => [StoreTranslationSection::EmailTemplates],
        'store_settings' => [StoreTranslationSection::StoreSettings],
        'static_texts' => [StoreTranslationSection::StaticTexts],
    ];

    public const FAILED_LIST_LIMIT = 50;

    public function __construct(
        private readonly StoreTranslationRunRepositoryContract $runs,
        private readonly StoreTranslationItemRepositoryContract $items,
        private readonly AiUsageLogRepositoryContract $usage,
        private readonly LanguageRepositoryContract $languages,
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreTranslationRegistry $registry,
        private readonly StoreLanguageService $storeLanguages,
    ) {}

    /**
     * KPI cards: runs (full-store runs + per-product translations), runs this month,
     * active runs, items translated (+ languages), tokens, paid languages.
     *
     * @return array{runs: int, this_month: int, active: int, items: int, item_languages: int, tokens: int, paid_languages: int}
     */
    public function stats(Tenant $tenant): array
    {
        $runs = $this->runs->statsForTenant((int) $tenant->id);
        $products = $this->usage->productTranslationStats((int) $tenant->id);

        return [
            'runs' => $runs['runs'] + $products['calls'],
            'this_month' => $runs['this_month'] + $products['this_month'],
            'active' => $runs['active'],
            'items' => $runs['items'],
            'item_languages' => $runs['item_languages'],
            'tokens' => $runs['tokens'] + $products['tokens'],
            'paid_languages' => $runs['paid_languages'],
        ];
    }

    /**
     * The run followed live, with the progress of each page section and the failed items.
     *
     * @return array{run: StoreTranslationRun|null, name: string, sections: list<array{key: string, done: int, failed: int, total: int, percent: int, state: string}>, failed: Collection<int, StoreTranslationItem>}
     */
    public function current(Tenant $tenant): array
    {
        $run = $this->runs->currentForTenant((int) $tenant->id);

        if ($run === null) {
            return ['run' => null, 'name' => '', 'sections' => [], 'failed' => collect()];
        }

        [$counts, $totals, $failed] = $tenant->run(function () use ($run): array {
            $totals = [];

            foreach (StoreTranslationSection::cases() as $section) {
                $totals[$section->value] = count($this->registry->source($section)->keys($run->language_code));
            }

            return [$this->items->countsBySection($run->language_code), $totals, $this->items->failedItems($run->language_code, self::FAILED_LIST_LIMIT)];
        });

        $sections = [];

        foreach (self::SECTION_GROUPS as $key => $members) {
            $done = 0;
            $failedCount = 0;
            $total = 0;

            foreach ($members as $section) {
                $total += $totals[$section->value] ?? 0;
                $done += $counts[$section->value]['done'] ?? 0;
                $failedCount += $counts[$section->value]['failed'] ?? 0;
            }

            $done = min($done, $total);
            $sections[] = [
                'key' => $key,
                'done' => $done,
                'failed' => $failedCount,
                'total' => $total,
                'percent' => $total > 0 ? (int) floor($done * 100 / $total) : 0,
                'state' => match (true) {
                    $total === 0 || $done >= $total => 'done',
                    $done === 0 && $failedCount === 0 => 'waiting',
                    default => 'partial',
                },
            ];
        }

        return ['run' => $run, 'name' => $this->languageName($run->language_code), 'sections' => $sections, 'failed' => $failed];
    }

    /**
     * Languages card: every bought language and every language with a run, with its
     * latest run and the runs / items / tokens totals.
     *
     * @return Collection<int, array{code: string, name: string, run: StoreTranslationRun|null, runs: int, items: int, tokens: int}>
     */
    public function languages(Tenant $tenant): Collection
    {
        $totals = $this->runs->totalsByLanguage((int) $tenant->id);

        return $this->storeLanguages->storeLanguages($tenant)
            ->filter(static fn (array $row): bool => $row['run'] !== null || ! $row['core'])
            ->map(fn (array $row): array => [
                'code' => $row['code'],
                'name' => $row['language']?->name ?: strtoupper($row['code']),
                'run' => $row['run'],
                'runs' => $totals[$row['code']]['runs'] ?? 0,
                'items' => $totals[$row['code']]['items'] ?? 0,
                'tokens' => $totals[$row['code']]['tokens'] ?? 0,
            ])
            ->values();
    }

    /**
     * Languages the store can still buy (074a select), each with its admin USD price.
     *
     * @return Collection<int, Language>
     */
    public function purchasable(Tenant $tenant): Collection
    {
        return $this->storeLanguages->purchasable($tenant)->map(static fn (array $row): Language => $row['language'])->values();
    }

    /**
     * Buy a language from this page: the shared language purchase + invoice checkout,
     * returning to the AI translation page.
     *
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws PaymentGatewayException
     */
    public function startPurchase(Tenant $tenant, int $languageId, PaymentGatewayCode $gateway, ?string $email): array
    {
        return $this->storeLanguages->startPurchase(
            $tenant,
            $this->storeLanguages->findPurchasable($tenant, $languageId),
            $gateway,
            $email,
            static fn (PaymentLog $log): array => [
                'success' => vendor_route('settings.ai-translation.payment.result', ['payment' => $log->id]),
                'cancel' => vendor_route('settings.ai-translation.payment.cancel', ['payment' => $log->id]),
            ],
        );
    }

    /**
     * "Run again" of a completed run (latest of its language): a new run that only
     * translates new or changed content (items with an unchanged source are skipped).
     */
    public function runAgain(Tenant $tenant, int $runId): StoreTranslationRun
    {
        $run = $this->latestOf($tenant, $runId);

        if ($run->status !== TranslationRunStatus::Completed) {
            throw TranslationRuleException::runNotRepeatable();
        }

        return $this->storeLanguages->queueRun((int) $tenant->id, $run->language_id, $run->language_code, null, TranslationRunTrigger::Delta);
    }

    /**
     * "Retry" of a failed run (latest of its language): resumes failed / changed items.
     */
    public function retry(Tenant $tenant, int $runId): StoreTranslationRun
    {
        return $this->storeLanguages->retry($tenant, $this->latestOf($tenant, $runId)->language_code);
    }

    /**
     * Product names of per-product history rows ("store_product:<id>" subjects).
     *
     * @param  list<string|null>  $subjects
     * @return array<int, string> product id => name
     */
    public function productNames(Tenant $tenant, array $subjects): array
    {
        $ids = [];

        foreach ($subjects as $subject) {
            if (is_string($subject) && str_starts_with($subject, 'store_product:')) {
                $ids[] = (int) substr($subject, 14);
            }
        }

        if ($ids === []) {
            return [];
        }

        return $tenant->run(fn (): array => $this->products->findManyKeyed(array_values(array_unique($ids)))
            ->load('centralProduct')
            ->map(static fn (StoreProduct $product): string => (string) ($product->name ?: $product->centralProduct?->name ?: '#'.$product->id))
            ->all());
    }

    /**
     * @return array<string, string> code => name
     */
    public function languageNames(): array
    {
        return $this->languages->query()->get()->mapWithKeys(static fn (Language $language): array => [$language->code => (string) $language->name])->all();
    }

    /**
     * Language codes whose name or code matches a search term.
     *
     * @return list<string>
     */
    public function languageCodesMatching(string $term): array
    {
        $term = mb_strtolower(trim($term));

        return array_values(array_keys(array_filter(
            $this->languageNames(),
            static fn (string $name, string $code): bool => str_contains(mb_strtolower($name), $term) || str_contains($code, $term),
            ARRAY_FILTER_USE_BOTH,
        )));
    }

    public function paymentLog(Tenant $tenant, int $logId): ?PaymentLog
    {
        return $this->storeLanguages->paymentLog($tenant, $logId);
    }

    public function confirmPayment(PaymentLog $log): PaymentLog
    {
        return $this->storeLanguages->confirmPayment($log);
    }

    public function cancelPayment(PaymentLog $log): PaymentLog
    {
        return $this->storeLanguages->cancelPayment($log);
    }

    public function languageNameById(int $languageId): string
    {
        return $this->storeLanguages->languageName($languageId);
    }

    private function languageName(string $code): string
    {
        return $this->languageNames()[$code] ?? strtoupper($code);
    }

    private function latestOf(Tenant $tenant, int $runId): StoreTranslationRun
    {
        $run = $this->runs->findForTenant((int) $tenant->id, $runId);
        $latest = $run !== null ? $this->runs->latestByLanguage((int) $tenant->id)->get($run->language_code) : null;

        if ($run === null || $latest === null || $latest->id !== $run->id) {
            throw TranslationRuleException::runNotRetryable();
        }

        return $run;
    }
}
